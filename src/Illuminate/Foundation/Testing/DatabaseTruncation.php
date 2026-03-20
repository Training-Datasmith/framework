<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection_Interface;
use Illuminate\Foundation\Testing\Traits\Can_Configure_Migration_Commands;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
trait Database_Truncation
{
    use Can_Configure_Migration_Commands;
    /**
     * The cached names of the database tables for each connection.
     */
    protected static array $all_tables;
    /**
     * Truncate the database tables for all configured connections.
     */
    protected function truncate_database_tables(): void
    {
        $this->before_truncating_database();
        // Migrate and seed the database on first run...
        if (!Refresh_Database_State::$migrated) {
            $this->artisan('migrate:fresh', $this->migrate_fresh_using());
            $this->app[Kernel::class]->set_artisan(null);
            Refresh_Database_State::$migrated = true;
            return;
        }
        // Always clear any test data on subsequent runs...
        $this->truncate_tables_for_all_connections();
        if ($seeder = $this->seeder()) {
            // Use a specific seeder class...
            $this->artisan('db:seed', ['--class' => $seeder]);
        } elseif ($this->should_seed()) {
            // Use the default seeder class...
            $this->artisan('db:seed');
        }
        $this->after_truncating_database();
    }
    /**
     * Truncate the database tables for all configured connections.
     */
    protected function truncate_tables_for_all_connections(): void
    {
        $database = $this->app->make('db');
        (new Collection($this->connections_to_truncate()))->each(function ($name) use ($database): void {
            $connection = $database->connection($name);
            $connection->get_schema_builder()->without_foreign_key_constraints(fn() => $this->truncate_tables_for_connection($connection, $name));
        });
    }
    /**
     * Truncate the database tables for the given database connection.
     */
    protected function truncate_tables_for_connection(Connection_Interface $connection, ?string $name): void
    {
        $dispatcher = $connection->get_event_dispatcher();
        $connection->unset_event_dispatcher();
        (new Collection($this->get_all_tables_for_connection($connection, $name)))->when($this->tables_to_truncate($connection, $name), fn(Collection $tables, array $tables_to_truncate): \Illuminate\Support\Collection => $tables->filter(fn(array $table) => $this->table_exists_in($table, $tables_to_truncate)), function (Collection $tables) use ($connection, $name) {
            $except_tables = $this->except_tables($connection, $name);
            return $tables->reject(fn(array $table) => $this->table_exists_in($table, $except_tables));
        })->each(function (array $table) use ($connection): void {
            $connection->without_table_prefix(function ($connection) use ($table): void {
                $table = $connection->table($table['schema_qualified_name']);
                if ($table->exists()) {
                    $table->truncate();
                }
            });
        });
        $connection->set_event_dispatcher($dispatcher);
    }
    /**
     * Get all the tables that belong to the connection.
     */
    protected function get_all_tables_for_connection(Connection_Interface $connection, ?string $name): array
    {
        if (isset(static::$all_tables[$name])) {
            return static::$all_tables[$name];
        }
        $schema = $connection->get_schema_builder();
        return static::$all_tables[$name] = Arr::from($schema->get_tables($schema->get_current_schema_listing()));
    }
    /**
     * Determine if a table exists in the given list, with or without its schema.
     */
    protected function table_exists_in(array $table, array $tables): bool
    {
        return $table['schema'] ? !empty(array_intersect([$table['name'], $table['schema_qualified_name']], $tables)) : in_array($table['name'], $tables);
    }
    /**
     * The database connections that should have their tables truncated.
     */
    protected function connections_to_truncate(): array
    {
        return property_exists($this, 'connectionsToTruncate') ? $this->connections_to_truncate : [null];
    }
    /**
     * Get the tables that should be truncated.
     */
    protected function tables_to_truncate(Connection_Interface $connection, ?string $connection_name): ?array
    {
        return property_exists($this, 'tablesToTruncate') && is_array($this->tables_to_truncate) ? $this->tables_to_truncate[$connection_name] ?? $this->tables_to_truncate : null;
    }
    /**
     * Get the tables that should not be truncated.
     */
    protected function except_tables(Connection_Interface $connection, ?string $connection_name): array
    {
        $migrations = $this->app['config']->get('database.migrations');
        $migrations_table = is_array($migrations) ? $migrations['table'] ?? 'migrations' : $migrations;
        $migrations_table = $connection->get_table_prefix() . $migrations_table;
        return property_exists($this, 'exceptTables') && is_array($this->except_tables) ? array_merge($this->except_tables[$connection_name] ?? $this->except_tables, [$migrations_table]) : [$migrations_table];
    }
    /**
     * Perform any work that should take place before the database has started truncating.
     */
    protected function before_truncating_database(): void
    {
    }
    /**
     * Perform any work that should take place once the database has finished truncating.
     */
    protected function after_truncating_database(): void
    {
    }
}