<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
class Base_Command extends Command
{
    /**
     * Get all of the migration paths.
     *
     * @return string[]
     */
    protected function get_migration_paths()
    {
        // Here, we will check to see if a path option has been defined. If it has we will
        // use the path relative to the root of the installation folder so our database
        // migrations may be run for any customized path from within the application.
        if ($this->input->has_option('path') && $this->option('path')) {
            return (new Collection($this->option('path')))->map(fn($path) => !$this->using_real_path() ? $this->laravel->base_path() . '/' . $path : $path)->all();
        }
        return array_merge($this->migrator->paths(), [$this->get_migration_path()]);
    }
    /**
     * Determine if the given path(s) are pre-resolved "real" paths.
     */
    protected function using_real_path(): bool
    {
        return $this->input->has_option('realpath') && $this->option('realpath');
    }
    /**
     * Get the path to the migration directory.
     */
    protected function get_migration_path(): string
    {
        return $this->laravel->database_path() . DIRECTORY_SEPARATOR . 'migrations';
    }
}