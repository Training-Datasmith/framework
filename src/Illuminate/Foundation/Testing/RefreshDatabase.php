<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Traits\Can_Configure_Migration_Commands;
trait Refresh_Database
{
    use Can_Configure_Migration_Commands;
    /**
     * Define hooks to migrate the database before and after each test.
     */
    public function refresh_database(): void
    {
        $this->before_refreshing_database();
        if ($this->using_in_memory_databases()) {
            $this->restore_in_memory_database();
        }
        $this->refresh_test_database();
        $this->after_refreshing_database();
    }
    /**
     * Determine if any of the connections transacting is using in-memory databases.
     */
    protected function using_in_memory_databases(): bool
    {
        foreach ($this->connections_to_transact() as $name) {
            if ($this->using_in_memory_database($name)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Determine if a given database connection is an in-memory database.
     */
    protected function using_in_memory_database(?string $name = null): bool
    {
        if (is_null($name)) {
            $name = config('database.default');
        }
        return config("database.connections.{$name}.database") === ':memory:';
    }
    /**
     * Restore the in-memory database between tests.
     *
     * @return void
     */
    protected function restore_in_memory_database()
    {
        $database = $this->app->make('db');
        foreach ($this->connections_to_transact() as $name) {
            if (isset(Refresh_Database_State::$in_memory_connections[$name])) {
                $database->connection($name)->set_pdo(Refresh_Database_State::$in_memory_connections[$name]);
            }
        }
    }
    /**
     * Refresh a conventional test database.
     *
     * @return void
     */
    protected function refresh_test_database()
    {
        if (!Refresh_Database_State::$migrated) {
            $this->migrate_databases();
            $this->app[Kernel::class]->set_artisan(null);
            $this->update_local_cache_of_in_memory_databases();
            Refresh_Database_State::$migrated = true;
        }
        $this->begin_database_transaction();
    }
    /**
     * Update locally cached in-memory PDO connections after migration.
     *
     * @return void
     */
    protected function update_local_cache_of_in_memory_databases()
    {
        $database = $this->app->make('db');
        foreach ($this->connections_to_transact() as $name) {
            if ($this->using_in_memory_database($name)) {
                Refresh_Database_State::$in_memory_connections[$name] = $database->connection($name)->get_pdo();
            }
        }
    }
    /**
     * Migrate the database.
     *
     * @return void
     */
    protected function migrate_databases()
    {
        $this->artisan('migrate:fresh', $this->migrate_fresh_using());
    }
    /**
     * Begin a database transaction on the testing database.
     */
    public function begin_database_transaction(): void
    {
        $database = $this->app->make('db');
        $connections = $this->connections_to_transact();
        $this->app->instance('db.transactions', $transactions_manager = new Database_Transactions_Manager($connections));
        foreach ($connections as $name) {
            $connection = $database->connection($name);
            $connection->set_transaction_manager($transactions_manager);
            if ($this->using_in_memory_database($name)) {
                Refresh_Database_State::$in_memory_connections[$name] ??= $connection->get_pdo();
            }
            $dispatcher = $connection->get_event_dispatcher();
            $connection->unset_event_dispatcher();
            $connection->begin_transaction();
            $connection->set_event_dispatcher($dispatcher);
        }
        $this->before_application_destroyed(function () use ($database): void {
            foreach ($this->connections_to_transact() as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->get_event_dispatcher();
                $connection->unset_event_dispatcher();
                if ($connection->get_pdo() && !$connection->get_pdo()->in_transaction()) {
                    Refresh_Database_State::$migrated = false;
                }
                $connection->roll_back();
                $connection->set_event_dispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }
    /**
     * The database connections that should have transactions.
     *
     * @return array
     */
    protected function connections_to_transact()
    {
        return property_exists($this, 'connectionsToTransact') ? $this->connections_to_transact : [config('database.default')];
    }
    /**
     * Perform any work that should take place before the database has started refreshing.
     *
     * @return void
     */
    protected function before_refreshing_database()
    {
        // ...
    }
    /**
     * Perform any work that should take place once the database has finished refreshing.
     *
     * @return void
     */
    protected function after_refreshing_database()
    {
        // ...
    }
}