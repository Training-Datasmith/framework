<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
class Sqlite_Schema_State extends Schema_State
{
    /**
     * Dump the database's schema into a file.
     *
     * @param  string  $path
     */
    public function dump(Connection $connection, $path): void
    {
        $process = $this->make_process($this->base_command() . ' ".schema --indent"')->set_timeout(null)->must_run(null, array_merge($this->base_variables($this->connection->get_config()), []));
        $migrations = preg_replace('/CREATE TABLE sqlite_.+?\);[\r\n]+/is', '', $process->get_output());
        $this->files->put($path, $migrations . PHP_EOL);
        if ($this->has_migration_table()) {
            $this->append_migration_data($path);
        }
    }
    /**
     * Append the migration data to the schema dump.
     *
     * @return void
     */
    protected function append_migration_data(string $path)
    {
        $process = $this->make_process($this->base_command() . ' ".dump \'' . $this->get_migration_table() . '\'"')->must_run(null, array_merge($this->base_variables($this->connection->get_config()), []));
        $migrations = (new Collection(preg_split("/\r\n|\n|\r/", $process->get_output())))->filter(fn($line): bool => preg_match('/^\s*(--|INSERT\s)/iu', $line) === 1 && strlen($line) > 0)->all();
        $this->files->append($path, implode(PHP_EOL, $migrations) . PHP_EOL);
    }
    /**
     * Load the given schema file into the database.
     *
     * @param  string  $path
     */
    public function load($path): void
    {
        $database = $this->connection->get_database_name();
        if ($database === ':memory:' || str_contains($database, '?mode=memory') || str_contains($database, '&mode=memory')) {
            $this->connection->get_pdo()->exec($this->files->get($path));
            return;
        }
        $process = $this->make_process($this->base_command() . ' < "${:LARAVEL_LOAD_PATH}"');
        $process->must_run(null, array_merge($this->base_variables($this->connection->get_config()), ['LARAVEL_LOAD_PATH' => $path]));
    }
    /**
     * Get the base sqlite command arguments as a string.
     */
    protected function base_command(): string
    {
        return 'sqlite3 "${:LARAVEL_LOAD_DATABASE}"';
    }
    /**
     * Get the base variables for a dump / load command.
     */
    protected function base_variables(array $config): array
    {
        return ['LARAVEL_LOAD_DATABASE' => $config['database']];
    }
}