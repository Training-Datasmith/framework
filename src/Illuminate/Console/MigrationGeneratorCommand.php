<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Illuminate\Filesystem\Filesystem;
use function Illuminate\Filesystem\join_paths;
abstract class Migration_Generator_Command extends Command
{
    /**
     * Create a new migration generator command instance.
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files
    )
    {
        parent::__construct();
    }
    /**
     * Get the migration table name.
     *
     * @return string
     */
    abstract protected function migration_table_name();
    /**
     * Get the path to the migration stub file.
     *
     * @return string
     */
    abstract protected function migration_stub_file();
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $table = $this->migration_table_name();
        if ($this->migration_exists($table)) {
            $this->components->error('Migration already exists.');
            return 1;
        }
        $this->replace_migration_placeholders($this->create_base_migration($table), $table);
        $this->components->info('Migration created successfully.');
        return 0;
    }
    /**
     * Create a base migration file for the table.
     *
     * @return string
     */
    protected function create_base_migration(string $table)
    {
        return $this->laravel['migration.creator']->create('create_' . $table . '_table', $this->laravel->database_path('/migrations'));
    }
    /**
     * Replace the placeholders in the generated migration file.
     *
     * @param  string  $path
     * @param  string  $table
     * @return void
     */
    protected function replace_migration_placeholders($path, $table)
    {
        $stub = str_replace('{{table}}', $table, $this->files->get($this->migration_stub_file()));
        $this->files->put($path, $stub);
    }
    /**
     * Determine whether a migration for the table already exists.
     *
     * @return bool
     */
    protected function migration_exists(string $table)
    {
        return count($this->files->glob(join_paths($this->laravel->database_path('migrations'), '*_*_*_*_create_' . $table . '_table.php'))) !== 0;
    }
}