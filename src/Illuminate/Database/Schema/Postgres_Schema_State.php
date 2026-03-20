<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
class Postgres_Schema_State extends Schema_State
{
    /**
     * Dump the database's schema into a file.
     *
     * @param  string  $path
     */
    public function dump(Connection $connection, $path): void
    {
        $commands = new Collection([$this->base_dump_command() . ' --schema-only > ' . $path]);
        if ($this->has_migration_table()) {
            $commands->push($this->base_dump_command() . ' -t ' . $this->get_migration_table() . ' --data-only >> ' . $path);
        }
        $commands->map(function ($command, $path): void {
            $this->make_process($command)->must_run($this->output, array_merge($this->base_variables($this->connection->get_config()), ['LARAVEL_LOAD_PATH' => $path]));
        });
    }
    /**
     * Load the given schema file into the database.
     *
     * @param  string  $path
     */
    public function load($path): void
    {
        $command = 'pg_restore --no-owner --no-acl --clean --if-exists --host="${:LARAVEL_LOAD_HOST}" --port="${:LARAVEL_LOAD_PORT}" --username="${:LARAVEL_LOAD_USER}" --dbname="${:LARAVEL_LOAD_DATABASE}" "${:LARAVEL_LOAD_PATH}"';
        if (str_ends_with($path, '.sql')) {
            $command = 'psql --file="${:LARAVEL_LOAD_PATH}" --host="${:LARAVEL_LOAD_HOST}" --port="${:LARAVEL_LOAD_PORT}" --username="${:LARAVEL_LOAD_USER}" --dbname="${:LARAVEL_LOAD_DATABASE}"';
        }
        $process = $this->make_process($command);
        $process->must_run(null, array_merge($this->base_variables($this->connection->get_config()), ['LARAVEL_LOAD_PATH' => $path]));
    }
    /**
     * Get the name of the application's migration table.
     */
    protected function get_migration_table(): string
    {
        [$schema, $table] = $this->connection->get_schema_builder()->parse_schema_and_table($this->migration_table, withDefaultSchema: true);
        return $schema . '.' . $this->connection->get_table_prefix() . $table;
    }
    /**
     * Get the base dump command arguments for PostgreSQL as a string.
     */
    protected function base_dump_command(): string
    {
        return 'pg_dump --no-owner --no-acl --host="${:LARAVEL_LOAD_HOST}" --port="${:LARAVEL_LOAD_PORT}" --username="${:LARAVEL_LOAD_USER}" --dbname="${:LARAVEL_LOAD_DATABASE}"';
    }
    /**
     * Get the base variables for a dump / load command.
     */
    protected function base_variables(array $config): array
    {
        $config['host'] ??= '';
        return ['LARAVEL_LOAD_HOST' => is_array($config['host']) ? $config['host'][0] : $config['host'], 'LARAVEL_LOAD_PORT' => $config['port'] ?? '', 'LARAVEL_LOAD_USER' => $config['username'], 'PGPASSWORD' => $config['password'], 'LARAVEL_LOAD_DATABASE' => $config['database']];
    }
}