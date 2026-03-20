<?php

declare (strict_types=1);
namespace Illuminate\Database\Migrations;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
class Migration_Creator
{
    /**
     * The registered post create hooks.
     *
     * @var (\Closure(string, string): void)[]
     */
    protected $post_create = [];
    /**
     * Create a new migration creator instance.
     *
     * @param  string  $customStubPath
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files,
        /**
         * The custom app stubs directory.
         */
        protected $custom_stub_path
    )
    {
    }
    /**
     * Create a new migration at the given path.
     *
     * @param  string  $name
     * @param  string  $path
     * @param  string|null  $table
     * @param  bool  $create
     *
     * @throws \Exception
     */
    public function create($name, $path, $table = null, $create = false): string
    {
        $this->ensure_migration_doesnt_already_exist($name, $path);
        // First we will get the stub file for the migration, which serves as a type
        // of template for the migration. Once we have those we will populate the
        // various place-holders, save the file, and run the post create event.
        $stub = $this->get_stub($table, $create);
        $path = $this->get_path($name, $path);
        $this->files->ensure_directory_exists(dirname($path));
        $this->files->put($path, $this->populate_stub($stub, $table));
        // Next, we will fire any hooks that are supposed to fire after a migration is
        // created. Once that is done we'll be ready to return the full path to the
        // migration file so it can be used however it's needed by the developer.
        $this->fire_post_create_hooks($table, $path);
        return $path;
    }
    /**
     * Ensure that a migration with the given name doesn't already exist.
     *
     * @param  string  $name
     * @param  string|null  $migrationPath
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function ensure_migration_doesnt_already_exist($name, $migration_path = null)
    {
        if (!empty($migration_path)) {
            $migration_files = $this->files->glob($migration_path . '/*.php');
            foreach ($migration_files as $migration_file) {
                $this->files->require_once($migration_file);
            }
        }
        if (class_exists($class_name = $this->get_class_name($name))) {
            throw new InvalidArgumentException("A {$class_name} class already exists.");
        }
    }
    /**
     * Get the migration stub file.
     *
     * @param  string|null  $table
     * @param  bool  $create
     * @return string
     */
    protected function get_stub($table, $create)
    {
        if (is_null($table)) {
            $stub = $this->files->exists($custom_path = $this->custom_stub_path . '/migration.stub') ? $custom_path : $this->stub_path() . '/migration.stub';
        } elseif ($create) {
            $stub = $this->files->exists($custom_path = $this->custom_stub_path . '/migration.create.stub') ? $custom_path : $this->stub_path() . '/migration.create.stub';
        } else {
            $stub = $this->files->exists($custom_path = $this->custom_stub_path . '/migration.update.stub') ? $custom_path : $this->stub_path() . '/migration.update.stub';
        }
        return $this->files->get($stub);
    }
    /**
     * Populate the place-holders in the migration stub.
     *
     * @param  string  $stub
     * @param  string|null  $table
     * @return string
     */
    protected function populate_stub($stub, $table)
    {
        // Here we will replace the table place-holders with the table specified by
        // the developer, which is useful for quickly creating a tables creation
        // or update migration from the console instead of typing it manually.
        if (!is_null($table)) {
            return str_replace(['DummyTable', '{{ table }}', '{{table}}'], $table, $stub);
        }
        return $stub;
    }
    /**
     * Get the class name of a migration name.
     *
     * @param  string  $name
     * @return class-string<\Illuminate\Database\Migrations\Migration>
     */
    protected function get_class_name($name)
    {
        return Str::studly($name);
    }
    /**
     * Get the full path to the migration.
     */
    protected function get_path(string $name, string $path): string
    {
        return $path . '/' . $this->get_date_prefix() . '_' . $name . '.php';
    }
    /**
     * Fire the registered post create hooks.
     *
     * @param  string|null  $table
     * @param  string  $path
     * @return void
     */
    protected function fire_post_create_hooks($table, $path)
    {
        foreach ($this->post_create as $callback) {
            $callback($table, $path);
        }
    }
    /**
     * Register a post migration create hook.
     *
     * @param  (\Closure(string, string): void)  $callback
     */
    public function after_create(Closure $callback): void
    {
        $this->post_create[] = $callback;
    }
    /**
     * Get the date prefix for the migration.
     */
    protected function get_date_prefix(): string
    {
        return date('Y_m_d_His');
    }
    /**
     * Get the path to the stubs.
     */
    public function stub_path(): string
    {
        return __DIR__ . '/stubs';
    }
    /**
     * Get the filesystem instance.
     */
    public function get_filesystem(): \Illuminate\Filesystem\Filesystem
    {
        return $this->files;
    }
}