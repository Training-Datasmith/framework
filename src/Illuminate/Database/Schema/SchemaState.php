<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
abstract class Schema_State
{
    /**
     * The filesystem instance.
     */
    protected \Illuminate\Filesystem\Filesystem $files;
    /**
     * The name of the application's migration table.
     *
     * @var string
     */
    protected $migration_table = 'migrations';
    /**
     * The process factory callback.
     *
     * @var callable
     */
    protected $process_factory;
    /**
     * The output callable instance.
     *
     * @var callable
     */
    protected $output;
    /**
     * Create a new dumper instance.
     */
    public function __construct(
        /**
         * The connection instance.
         */
        protected \Illuminate\Database\Connection $connection,
        ?Filesystem $files = null,
        ?callable $process_factory = null
    )
    {
        $this->files = $files ?: new Filesystem();
        $this->process_factory = $process_factory ?: fn(...$arguments): \Symfony\Component\Process\Process => Process::from_shell_commandline(...$arguments)->set_timeout(null);
        $this->handle_output_using(function (): void {
        });
    }
    /**
     * Dump the database's schema into a file.
     *
     * @param  string  $path
     * @return void
     */
    abstract public function dump(Connection $connection, $path);
    /**
     * Load the given schema file into the database.
     *
     * @param  string  $path
     * @return void
     */
    abstract public function load($path);
    /**
     * Create a new process instance.
     *
     * @param  mixed  ...$arguments
     * @return \Symfony\Component\Process\Process
     */
    public function make_process(...$arguments)
    {
        return call_user_func($this->process_factory, ...$arguments);
    }
    /**
     * Determine if the current connection has a migration table.
     */
    public function has_migration_table(): bool
    {
        return $this->connection->get_schema_builder()->has_table($this->migration_table);
    }
    /**
     * Get the name of the application's migration table.
     */
    protected function get_migration_table(): string
    {
        return $this->connection->get_table_prefix() . $this->migration_table;
    }
    /**
     * Specify the name of the application's migration table.
     *
     * @return $this
     */
    public function with_migration_table(string $table)
    {
        $this->migration_table = $table;
        return $this;
    }
    /**
     * Specify the callback that should be used to handle process output.
     *
     * @return $this
     */
    public function handle_output_using(callable $output)
    {
        $this->output = $output;
        return $this;
    }
}