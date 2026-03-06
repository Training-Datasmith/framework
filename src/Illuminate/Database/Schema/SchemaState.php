<?php

declare(strict_types=1);

namespace Illuminate\Database\Schema;

use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

abstract class SchemaState
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
    protected $migrationTable = 'migrations';

    /**
     * The process factory callback.
     *
     * @var callable
     */
    protected $processFactory;

    /**
     * The output callable instance.
     *
     * @var callable
     */
    protected $output;

    /**
     * Create a new dumper instance.
     */
    public function __construct(/**
     * The connection instance.
     */
        protected \Illuminate\Database\Connection $connection,
        ?Filesystem $files = null,
        ?callable $processFactory = null
    ) {
        $this->files = $files ?: new Filesystem();

        $this->processFactory = $processFactory ?: (fn (...$arguments): \Symfony\Component\Process\Process => Process::fromShellCommandline(...$arguments)->setTimeout(null));

        $this->handleOutputUsing(function (): void {

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
    public function makeProcess(...$arguments)
    {
        return call_user_func($this->processFactory, ...$arguments);
    }

    /**
     * Determine if the current connection has a migration table.
     */
    public function hasMigrationTable(): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable($this->migrationTable);
    }

    /**
     * Get the name of the application's migration table.
     */
    protected function getMigrationTable(): string
    {
        return $this->connection->getTablePrefix().$this->migrationTable;
    }

    /**
     * Specify the name of the application's migration table.
     *
     * @return $this
     */
    public function withMigrationTable(string $table)
    {
        $this->migrationTable = $table;

        return $this;
    }

    /**
     * Specify the callback that should be used to handle process output.
     *
     * @return $this
     */
    public function handleOutputUsing(callable $output)
    {
        $this->output = $output;

        return $this;
    }
}
