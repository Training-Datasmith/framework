<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Database\Query_Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
class Sq_Lite_Builder extends Builder
{
    /**
     * Create a database in the schema.
     *
     * @param  string  $name
     */
    public function create_database($name): bool
    {
        return File::put($name, '') !== false;
    }
    /**
     * Drop a database from the schema if the database exists.
     *
     * @param  string  $name
     */
    public function drop_database_if_exists($name): bool
    {
        if (!File::exists($name)) {
            return true;
        }
        return File::delete($name);
    }
    /** @inheritDoc */
    public function get_tables($schema = null): array
    {
        try {
            $with_size = $this->connection->scalar($this->grammar->compile_dbstat_exists());
        } catch (Query_Exception) {
            $with_size = false;
        }
        if (version_compare($this->connection->get_server_version(), '3.37.0', '<')) {
            $schema ??= array_column($this->get_schemas(), 'name');
            $tables = [];
            foreach (Arr::wrap($schema) as $name) {
                $tables = array_merge($tables, $this->connection->select_from_write_connection($this->grammar->compile_legacy_tables($name, $with_size)));
            }
            return $this->connection->get_post_processor()->process_tables($tables);
        }
        return $this->connection->get_post_processor()->process_tables($this->connection->select_from_write_connection($this->grammar->compile_tables($schema)));
    }
    /** @inheritDoc */
    public function get_views($schema = null): array
    {
        $schema ??= array_column($this->get_schemas(), 'name');
        $views = [];
        foreach (Arr::wrap($schema) as $name) {
            $views = array_merge($views, $this->connection->select_from_write_connection($this->grammar->compile_views($name)));
        }
        return $this->connection->get_post_processor()->process_views($views);
    }
    /** @inheritDoc */
    public function get_columns($table)
    {
        [$schema, $table] = $this->parse_schema_and_table($table);
        $table = $this->connection->get_table_prefix() . $table;
        return $this->connection->get_post_processor()->process_columns($this->connection->select_from_write_connection($this->grammar->compile_columns($schema, $table)));
    }
    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function drop_all_tables()
    {
        foreach ($this->get_current_schema_listing() as $schema) {
            $database = $schema === 'main' ? $this->connection->get_database_name() : (array_column($this->get_schemas(), 'path', 'name')[$schema] ?: ':memory:');
            if ($database !== ':memory:' && !str_contains($database, '?mode=memory') && !str_contains($database, '&mode=memory')) {
                $this->refresh_database_file($database);
            } else {
                $this->pragma('writable_schema', 1);
                $this->connection->statement($this->grammar->compile_drop_all_tables($schema));
                $this->pragma('writable_schema', 0);
                $this->connection->statement($this->grammar->compile_rebuild($schema));
            }
        }
    }
    /**
     * Drop all views from the database.
     *
     * @return void
     */
    public function drop_all_views()
    {
        foreach ($this->get_current_schema_listing() as $schema) {
            $this->pragma('writable_schema', 1);
            $this->connection->statement($this->grammar->compile_drop_all_views($schema));
            $this->pragma('writable_schema', 0);
            $this->connection->statement($this->grammar->compile_rebuild($schema));
        }
    }
    /**
     * Get the value for the given pragma name or set the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function pragma($key, $value = null)
    {
        return is_null($value) ? $this->connection->scalar($this->grammar->pragma($key)) : $this->connection->statement($this->grammar->pragma($key, $value));
    }
    /**
     * Empty the database file.
     *
     * @param  string|null  $path
     */
    public function refresh_database_file($path = null): void
    {
        file_put_contents($path ?? $this->connection->get_database_name(), '');
    }
    /**
     * Get the names of current schemas for the connection.
     *
     * @return string[]|null
     */
    public function get_current_schema_listing(): null
    {
        return ['main'];
    }
}