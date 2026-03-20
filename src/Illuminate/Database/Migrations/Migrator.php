<?php

declare (strict_types=1);
namespace Illuminate\Database\Migrations;

use Closure;
use Illuminate\Console\View\Components\Bullet_List;
use Illuminate\Console\View\Components\Info;
use Illuminate\Console\View\Components\Task;
use Illuminate\Console\View\Components\Two_Column_Detail;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection_Resolver_Interface as Resolver;
use Illuminate\Database\Events\Migration_Ended;
use Illuminate\Database\Events\Migrations_Ended;
use Illuminate\Database\Events\Migration_Skipped;
use Illuminate\Database\Events\Migrations_Started;
use Illuminate\Database\Events\Migration_Started;
use Illuminate\Database\Events\No_Pending_Migrations;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Console\Output\Output_Interface;
class Migrator
{
    /**
     * The custom connection resolver callback.
     *
     * @var (\Closure(\Illuminate\Database\ConnectionResolverInterface, ?string): \Illuminate\Database\Connection)|null
     */
    protected static $connection_resolver_callback;
    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $connection;
    /**
     * The paths to all of the migration files.
     *
     * @var string[]
     */
    protected $paths = [];
    /**
     * The paths that have already been required.
     *
     * @var array<string, \Illuminate\Database\Migrations\Migration|null>
     */
    protected static $required_path_cache = [];
    /**
     * The output interface implementation.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;
    /**
     * The pending migrations to skip.
     *
     * @var list<string>
     */
    protected static $without_migrations = [];
    /**
     * Create a new migrator instance.
     */
    public function __construct(
        /**
         * The migration repository implementation.
         */
        protected \Illuminate\Database\Migrations\Migration_Repository_Interface $repository,
        /**
         * The connection resolver instance.
         */
        protected \Illuminate\Database\Connection_Resolver_Interface $resolver,
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files,
        /**
         * The event dispatcher instance.
         */
        protected ?\Illuminate\Contracts\Events\Dispatcher $events = null
    )
    {
    }
    /**
     * Run the pending migrations at a given path.
     *
     * @param  string[]|string  $paths
     * @param  array<string, mixed>  $options
     * @return string[]
     */
    public function run($paths = [], array $options = [])
    {
        // Once we grab all of the migration files for the path, we will compare them
        // against the migrations that have already been run for this package then
        // run each of the outstanding migrations against a database connection.
        $files = $this->get_migration_files($paths);
        $this->require_files($migrations = $this->pending_migrations($files, $this->repository->get_ran()));
        // Once we have all these migrations that are outstanding we are ready to run
        // we will go ahead and run them "up". This will execute each migration as
        // an operation against a database. Then we'll return this list of them.
        $this->run_pending($migrations, $options);
        return $migrations;
    }
    /**
     * Get the migration files that have not yet run.
     *
     * @param  string[]  $files
     * @param  string[]  $ran
     * @return string[]
     */
    protected function pending_migrations($files, $ran)
    {
        $migrations_to_skip = $this->migrations_to_skip();
        return (new Collection($files))->reject(fn($file): bool => in_array($migration_name = $this->get_migration_name($file), $ran) || in_array($migration_name, $migrations_to_skip))->values()->all();
    }
    /**
     * Get list of pending migrations to skip.
     *
     * @return list<string>
     */
    protected function migrations_to_skip()
    {
        return (new Collection(self::$without_migrations))->map($this->get_migration_name(...))->all();
    }
    /**
     * Run an array of migrations.
     *
     * @param  string[]  $migrations
     * @param  array<string, mixed>  $options
     */
    public function run_pending(array $migrations, array $options = []): void
    {
        // First we will just make sure that there are any migrations to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the migrations have been run against this database system.
        if (count($migrations) === 0) {
            $this->fire_migration_event(new No_Pending_Migrations('up'));
            $this->write(Info::class, 'Nothing to migrate');
            return;
        }
        // Next, we will get the next batch number for the migrations so we can insert
        // correct batch number in the database migrations repository when we store
        // each migration's execution. We will also extract a few of the options.
        $batch = $this->repository->get_next_batch_number();
        $pretend = $options['pretend'] ?? false;
        $step = $options['step'] ?? false;
        $this->fire_migration_event(new Migrations_Started('up', $options));
        $this->write(Info::class, 'Running migrations.');
        // Once we have the array of migrations, we will spin through them and run the
        // migrations "up" so the changes are made to the databases. We'll then log
        // that the migration was run so we don't repeat it next time we execute.
        foreach ($migrations as $file) {
            $this->run_up($file, $batch, $pretend);
            if ($step) {
                $batch++;
            }
        }
        $this->fire_migration_event(new Migrations_Ended('up', $options));
        $this->output?->writeln('');
    }
    /**
     * Run "up" a migration instance.
     *
     * @param  string  $file
     * @param  int  $batch
     * @param  bool  $pretend
     * @return void
     */
    protected function run_up($file, $batch, $pretend)
    {
        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $migration = $this->resolve_path($file);
        $name = $this->get_migration_name($file);
        if ($pretend) {
            return $this->pretend_to_run($migration, 'up');
        }
        $should_run_migration = $migration instanceof Migration ? $migration->should_run() : true;
        if (!$should_run_migration) {
            $this->fire_migration_event(new Migration_Skipped($name));
            $this->write(Task::class, $name, fn() => Migration_Result::Skipped->value);
        } else {
            $this->write(Task::class, $name, fn() => $this->run_migration($migration, 'up'));
            // Once we have run a migrations class, we will log that it was run in this
            // repository so that we don't try to run it next time we do a migration
            // in the application. A migration repository keeps the migrate order.
            $this->repository->log($name, $batch);
        }
    }
    /**
     * Rollback the last migration operation.
     *
     * @param  string[]|string  $paths
     * @param  array<string, mixed>  $options
     * @return string[]
     */
    public function rollback($paths = [], array $options = [])
    {
        // We want to pull in the last batch of migrations that ran on the previous
        // migration operation. We'll then reverse those migrations and run each
        // of them "down" to reverse the last migration "operation" which ran.
        $migrations = $this->get_migrations_for_rollback($options);
        if (count($migrations) === 0) {
            $this->fire_migration_event(new No_Pending_Migrations('down'));
            $this->write(Info::class, 'Nothing to rollback.');
            return [];
        }
        return tap($this->rollback_migrations($migrations, $paths, $options), function (): void {
            $this->output?->writeln('');
        });
    }
    /**
     * Get the migrations for a rollback operation.
     *
     * @param  array<string, mixed>  $options
     * @return array{id: int, migration: string, batch: int}[]
     */
    protected function get_migrations_for_rollback(array $options)
    {
        if (($steps = $options['step'] ?? 0) > 0) {
            return $this->repository->get_migrations($steps);
        }
        if (($batch = $options['batch'] ?? 0) > 0) {
            return $this->repository->get_migrations_by_batch($batch);
        }
        return $this->repository->get_last();
    }
    /**
     * Rollback the given migrations.
     *
     * @param  string[]|string  $paths
     * @param  array<string, mixed>  $options
     * @return string[]
     */
    protected function rollback_migrations(array $migrations, $paths, array $options): array
    {
        $rolled_back = [];
        $this->require_files($files = $this->get_migration_files($paths));
        $this->fire_migration_event(new Migrations_Started('down', $options));
        $this->write(Info::class, 'Rolling back migrations.');
        // Next we will run through all of the migrations and call the "down" method
        // which will reverse each migration in order. This getLast method on the
        // repository already returns these migration's names in reverse order.
        foreach ($migrations as $migration) {
            $migration = (object) $migration;
            if (!$file = Arr::get($files, $migration->migration)) {
                $this->write(Two_Column_Detail::class, $migration->migration, '<fg=yellow;options=bold>Migration not found</>');
                continue;
            }
            $rolled_back[] = $file;
            $this->run_down($file, $migration, $options['pretend'] ?? false);
        }
        $this->fire_migration_event(new Migrations_Ended('down', $options));
        return $rolled_back;
    }
    /**
     * Rolls all of the currently applied migrations back.
     *
     * @param  string[]|string  $paths
     * @param  bool  $pretend
     * @return array
     */
    public function reset($paths = [], $pretend = false)
    {
        // Next, we will reverse the migration list so we can run them back in the
        // correct order for resetting this database. This will allow us to get
        // the database back into its "empty" state ready for the migrations.
        $migrations = array_reverse($this->repository->get_ran());
        if (count($migrations) === 0) {
            $this->write(Info::class, 'Nothing to rollback.');
            return [];
        }
        return tap($this->reset_migrations($migrations, Arr::wrap($paths), $pretend), function (): void {
            $this->output?->writeln('');
        });
    }
    /**
     * Reset the given migrations.
     *
     * @param  string[]  $migrations
     * @param  string[]  $paths
     * @param  bool  $pretend
     */
    protected function reset_migrations(array $migrations, array $paths, $pretend = false): array
    {
        // Since the getRan method that retrieves the migration name just gives us the
        // migration name, we will format the names into objects with the name as a
        // property on the objects so that we can pass it to the rollback method.
        $migrations = (new Collection($migrations))->map(fn($m) => (object) ['migration' => $m])->all();
        return $this->rollback_migrations($migrations, $paths, compact('pretend'));
    }
    /**
     * Run "down" a migration instance.
     *
     * @param  string  $file
     * @param  object  $migration
     * @param  bool  $pretend
     * @return void
     */
    protected function run_down($file, $migration, $pretend)
    {
        // First we will get the file name of the migration so we can resolve out an
        // instance of the migration. Once we get an instance we can either run a
        // pretend execution of the migration or we can run the real migration.
        $instance = $this->resolve_path($file);
        $name = $this->get_migration_name($file);
        if ($pretend) {
            return $this->pretend_to_run($instance, 'down');
        }
        $this->write(Task::class, $name, fn() => $this->run_migration($instance, 'down'));
        // Once we have successfully run the migration "down" we will remove it from
        // the migration repository so it will be considered to have not been run
        // by the application then will be able to fire by any later operation.
        $this->repository->delete($migration);
    }
    /**
     * Run a migration inside a transaction if the database supports it.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return void
     */
    protected function run_migration($migration, $method)
    {
        $connection = $this->resolve_connection($migration->get_connection());
        $callback = function () use ($connection, $migration, $method): void {
            if (method_exists($migration, $method)) {
                $this->fire_migration_event(new Migration_Started($migration, $method));
                $this->run_method($connection, $migration, $method);
                $this->fire_migration_event(new Migration_Ended($migration, $method));
            }
        };
        $this->get_schema_grammar($connection)->supports_schema_transactions() && $migration->within_transaction ? $connection->transaction($callback) : $callback();
    }
    /**
     * Pretend to run the migrations.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return void
     */
    protected function pretend_to_run($migration, $method)
    {
        $name = $migration::class;
        $reflection_class = new ReflectionClass($migration);
        if ($reflection_class->is_anonymous()) {
            $name = $this->get_migration_name($reflection_class->get_file_name());
        }
        $this->write(Two_Column_Detail::class, $name);
        $this->write(Bullet_List::class, (new Collection($this->get_queries($migration, $method)))->map(fn($query) => $query['query']));
    }
    /**
     * Get all of the queries that would be run for a migration.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return array
     */
    protected function get_queries($migration, $method)
    {
        // Now that we have the connections we can resolve it and pretend to run the
        // queries against the database returning the array of raw SQL statements
        // that would get fired against the database system for this migration.
        $db = $this->resolve_connection($migration->get_connection());
        return $db->pretend(function () use ($db, $migration, $method): void {
            if (method_exists($migration, $method)) {
                $this->run_method($db, $migration, $method);
            }
        });
    }
    /**
     * Run a migration method on the given connection.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  object  $migration
     * @param  string  $method
     * @return void
     */
    protected function run_method($connection, $migration, $method)
    {
        $previous_connection = $this->resolver->get_default_connection();
        try {
            $this->resolver->set_default_connection($connection->get_name());
            $migration->{$method}();
        } finally {
            $this->resolver->set_default_connection($previous_connection);
        }
    }
    /**
     * Resolve a migration instance from a file.
     *
     * @return object
     */
    public function resolve(string $file)
    {
        $class = $this->get_migration_class($file);
        return new $class();
    }
    /**
     * Resolve a migration instance from a migration path.
     *
     * @return object
     */
    protected function resolve_path(string $path)
    {
        $class = $this->get_migration_class($this->get_migration_name($path));
        if (class_exists($class) && realpath($path) == (new ReflectionClass($class))->get_file_name()) {
            return new $class();
        }
        $migration = static::$required_path_cache[$path] ??= $this->files->get_require($path);
        if (is_object($migration)) {
            return method_exists($migration, '__construct') ? $this->files->get_require($path) : clone $migration;
        }
        return new $class();
    }
    /**
     * Generate a migration class name based on the migration file name.
     */
    protected function get_migration_class(string $migration_name): string
    {
        return Str::studly(implode('_', array_slice(explode('_', $migration_name), 4)));
    }
    /**
     * Get all of the migration files in a given path.
     *
     * @param  string|array  $paths
     * @return array<string, string>
     */
    public function get_migration_files($paths)
    {
        return (new Collection($paths))->flat_map(fn($path) => str_ends_with((string) $path, '.php') ? [$path] : $this->files->glob($path . '/*_*.php'))->filter()->values()->key_by(fn($file): string => $this->get_migration_name($file))->sort_by(fn($file, $key): string => $key)->all();
    }
    /**
     * Require in all the migration files in a given path.
     *
     * @param  string[]  $files
     */
    public function require_files(array $files): void
    {
        foreach ($files as $file) {
            $this->files->require_once($file);
        }
    }
    /**
     * Get the name of the migration.
     *
     * @param  string  $path
     */
    public function get_migration_name($path): string
    {
        return str_replace('.php', '', basename($path));
    }
    /**
     * Register a custom migration path.
     *
     * @param  string  $path
     */
    public function path($path): void
    {
        $this->paths = array_unique(array_merge($this->paths, [$path]));
    }
    /**
     * Get all of the custom migration paths.
     *
     * @return string[]
     */
    public function paths()
    {
        return $this->paths;
    }
    /**
     * Set the pending migrations to skip.
     *
     * @param  list<string>  $migrations
     */
    public static function without_migrations(array $migrations): void
    {
        static::$without_migrations = $migrations;
    }
    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function get_connection()
    {
        return $this->connection;
    }
    /**
     * Execute the given callback using the given connection as the default connection.
     *
     * @template TReturn
     *
     * @param  string  $name
     * @param  (callable(): TReturn)  $callback
     * @return mixed
     */
    public function using_connection($name, callable $callback)
    {
        $previous_connection = $this->resolver->get_default_connection();
        $this->set_connection($name);
        try {
            return $callback();
        } finally {
            $this->set_connection($previous_connection);
        }
    }
    /**
     * Set the default connection name.
     *
     * @param  string  $name
     */
    public function set_connection($name): void
    {
        if (!is_null($name)) {
            $this->resolver->set_default_connection($name);
        }
        $this->repository->set_source($name);
        $this->connection = $name;
    }
    /**
     * Resolve the database connection instance.
     *
     * @param  string  $connection
     * @return \Illuminate\Database\Connection
     */
    public function resolve_connection($connection)
    {
        if (static::$connection_resolver_callback) {
            return call_user_func(static::$connection_resolver_callback, $this->resolver, $connection ?: $this->connection);
        }
        return $this->resolver->connection($connection ?: $this->connection);
    }
    /**
     * Set a connection resolver callback.
     *
     * @param  \Closure(\Illuminate\Database\ConnectionResolverInterface, ?string): \Illuminate\Database\Connection  $callback
     */
    public static function resolve_connections_using(Closure $callback): void
    {
        static::$connection_resolver_callback = $callback;
    }
    /**
     * Get the schema grammar out of a migration connection.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @return \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected function get_schema_grammar($connection)
    {
        if (is_null($grammar = $connection->get_schema_grammar())) {
            $connection->use_default_schema_grammar();
            $grammar = $connection->get_schema_grammar();
        }
        return $grammar;
    }
    /**
     * Get the migration repository instance.
     */
    public function get_repository(): \Illuminate\Database\Migrations\Migration_Repository_Interface
    {
        return $this->repository;
    }
    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repository_exists()
    {
        return $this->repository->repository_exists();
    }
    /**
     * Determine if any migrations have been run.
     */
    public function has_run_any_migrations(): bool
    {
        return $this->repository_exists() && count($this->repository->get_ran()) > 0;
    }
    /**
     * Delete the migration repository data store.
     */
    public function delete_repository(): void
    {
        $this->repository->delete_repository();
    }
    /**
     * Get the file system instance.
     */
    public function get_filesystem(): \Illuminate\Filesystem\Filesystem
    {
        return $this->files;
    }
    /**
     * Set the output implementation that should be used by the console.
     *
     * @return $this
     */
    public function set_output(Output_Interface $output): static
    {
        $this->output = $output;
        return $this;
    }
    /**
     * Write to the console's output.
     *
     * @param  string  $component
     * @param  array<int, string>|string  ...$arguments
     * @return void
     */
    protected function write($component, ...$arguments)
    {
        if ($this->output && class_exists($component)) {
            (new $component($this->output))->render(...$arguments);
        } else {
            foreach ($arguments as $argument) {
                if (is_callable($argument)) {
                    $argument();
                }
            }
        }
    }
    /**
     * Fire the given event for the migration.
     *
     * @param  \Illuminate\Contracts\Database\Events\MigrationEvent  $event
     */
    public function fire_migration_event($event): void
    {
        $this->events?->dispatch($event);
    }
}