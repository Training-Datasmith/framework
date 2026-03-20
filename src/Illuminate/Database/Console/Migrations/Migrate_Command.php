<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Migrations;

use Illuminate\Console\Confirmable_Trait;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\Schema_Loaded;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Sq_Lite_Database_Does_Not_Exist_Exception;
use Illuminate\Database\Sql_Server_Connection;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use PDOException;
use RuntimeException;
use Symfony\Component\Console\Attribute\As_Command;
use Throwable;
#[As_Command(name: 'migrate')]
class Migrate_Command extends Base_Command implements Isolatable
{
    use Confirmable_Trait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--schema-path= : The path to a schema dump file}
                {--pretend : Dump the SQL queries that would be run}
                {--seed : Indicates if the seed task should be re-run}
                {--seeder= : The class name of the root seeder}
                {--step : Force the migrations to be run so they can be rolled back individually}
                {--graceful : Return a successful exit code even if an error occurs}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the database migrations';
    /**
     * Create a new migration command instance.
     */
    public function __construct(
        /**
         * The migrator instance.
         */
        protected \Illuminate\Database\Migrations\Migrator $migrator,
        /**
         * The event dispatcher instance.
         */
        protected \Illuminate\Contracts\Events\Dispatcher $dispatcher
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->confirm_to_proceed()) {
            return 1;
        }
        try {
            $this->run_migrations();
        } catch (Throwable $e) {
            if ($this->option('graceful')) {
                $this->components->warn($e->get_message());
                return 0;
            }
            throw $e;
        }
        return 0;
    }
    /**
     * Run the pending migrations.
     *
     * @return void
     */
    protected function run_migrations()
    {
        $this->migrator->using_connection($this->option('database'), function (): void {
            $this->prepare_database();
            // Next, we will check to see if a path option has been defined. If it has
            // we will use the path relative to the root of this installation folder
            // so that migrations may be run for any path within the applications.
            $this->migrator->set_output($this->output)->run($this->get_migration_paths(), ['pretend' => $this->option('pretend'), 'step' => $this->option('step')]);
            // Finally, if the "seed" option has been given, we will re-run the database
            // seed task to re-populate the database, which is convenient when adding
            // a migration and a seed at the same time, as it is only this command.
            if ($this->option('seed') && !$this->option('pretend')) {
                $this->call('db:seed', ['--class' => $this->option('seeder') ?: 'Database\Seeders\DatabaseSeeder', '--force' => true]);
            }
        });
    }
    /**
     * Prepare the migration database for running.
     *
     * @return void
     */
    protected function prepare_database()
    {
        if (!$this->repository_exists()) {
            $this->components->info('Preparing database.');
            $this->components->task('Creating migration table', fn(): bool => $this->call_silent('migrate:install', array_filter(['--database' => $this->option('database')])) == 0);
            $this->new_line();
        }
        if (!$this->migrator->has_run_any_migrations() && !$this->option('pretend')) {
            $this->load_schema_state();
        }
    }
    /**
     * Determine if the migrator repository exists.
     *
     * @return bool
     */
    protected function repository_exists()
    {
        return retry(2, fn() => $this->migrator->repository_exists(), 0, function ($e) {
            try {
                return $this->handle_missing_database($e->get_previous());
            } catch (Throwable) {
                return false;
            }
        });
    }
    /**
     * Attempt to create the database if it is missing.
     *
     * @return bool
     */
    protected function handle_missing_database(Throwable $e)
    {
        if ($e instanceof Sq_Lite_Database_Does_Not_Exist_Exception) {
            return $this->create_missing_sqlite_database($e->path);
        }
        $connection = $this->migrator->resolve_connection($this->option('database'));
        if (!$e instanceof PDOException) {
            return false;
        }
        if ($e->get_code() === 1049 && in_array($connection->get_driver_name(), ['mysql', 'mariadb']) || ($e->error_info[0] ?? null) == '08006' && $connection->get_driver_name() == 'pgsql' && Str::contains($e->get_message(), '"' . $connection->get_database_name() . '"')) {
            return $this->create_missing_my_sql_or_pgsql_database($connection);
        }
        return false;
    }
    /**
     * Create a missing SQLite database.
     *
     * @return bool
     * @throws \RuntimeException
     */
    protected function create_missing_sqlite_database(string $path)
    {
        if ($this->option('force')) {
            return touch($path);
        }
        if ($this->option('no-interaction')) {
            return false;
        }
        $this->components->warn('The SQLite database configured for this application does not exist: ' . $path);
        if (!confirm('Would you like to create it?', default: true)) {
            $this->components->info('Operation cancelled. No database was created.');
            throw new RuntimeException('Database was not created. Aborting migration.');
        }
        return touch($path);
    }
    /**
     * Create a missing MySQL or Postgres database.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @return bool
     *
     * @throws \RuntimeException
     */
    protected function create_missing_my_sql_or_pgsql_database($connection)
    {
        if ($this->laravel['config']->get("database.connections.{$connection->get_name()}.database") !== $connection->get_database_name()) {
            return false;
        }
        if (!$this->option('force') && $this->option('no-interaction')) {
            return false;
        }
        if (!$this->option('force') && !$this->option('no-interaction')) {
            $this->components->warn("The database '{$connection->get_database_name()}' does not exist on the '{$connection->get_name()}' connection.");
            if (!confirm('Would you like to create it?', default: true)) {
                $this->components->info('Operation cancelled. No database was created.');
                throw new RuntimeException('Database was not created. Aborting migration.');
            }
        }
        try {
            $this->laravel['config']->set("database.connections.{$connection->get_name()}.database", match ($connection->get_driver_name()) {
                'mysql', 'mariadb' => null,
                'pgsql' => 'postgres',
            });
            $this->laravel['db']->purge();
            $fresh_connection = $this->migrator->resolve_connection($this->option('database'));
            return tap($fresh_connection->unprepared(match ($connection->get_driver_name()) {
                'mysql', 'mariadb' => "CREATE DATABASE IF NOT EXISTS `{$connection->get_database_name()}`",
                'pgsql' => 'CREATE DATABASE "' . $connection->get_database_name() . '"',
            }), function (): void {
                $this->laravel['db']->purge();
            });
        } finally {
            $this->laravel['config']->set("database.connections.{$connection->get_name()}.database", $connection->get_database_name());
        }
    }
    /**
     * Load the schema state to seed the initial database schema structure.
     *
     * @return void
     */
    protected function load_schema_state()
    {
        $connection = $this->migrator->resolve_connection($this->option('database'));
        // First, we will make sure that the connection supports schema loading and that
        // the schema file exists before we proceed any further. If not, we will just
        // continue with the standard migration operation as normal without errors.
        if ($connection instanceof Sql_Server_Connection || !is_file($path = $this->schema_path($connection))) {
            return;
        }
        $this->components->info('Loading stored database schemas.');
        $this->components->task($path, function () use ($connection, $path): void {
            // Since the schema file will create the "migrations" table and reload it to its
            // proper state, we need to delete it here so we don't get an error that this
            // table already exists when the stored database schema file gets executed.
            $this->migrator->delete_repository();
            $connection->get_schema_state()->handle_output_using(function ($type, string|iterable $buffer): void {
                $this->output->write($buffer);
            })->load($path);
        });
        $this->new_line();
        // Finally, we will fire an event that this schema has been loaded so developers
        // can perform any post schema load tasks that are necessary in listeners for
        // this event, which may seed the database tables with some necessary data.
        $this->dispatcher->dispatch(new Schema_Loaded($connection, $path));
    }
    /**
     * Get the path to the stored schema for the given connection.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @return string
     */
    protected function schema_path($connection)
    {
        if ($this->option('schema-path')) {
            return $this->option('schema-path');
        }
        if (file_exists($path = database_path('schema/' . $connection->get_name() . '-schema.dump'))) {
            return $path;
        }
        return database_path('schema/' . $connection->get_name() . '-schema.sql');
    }
}