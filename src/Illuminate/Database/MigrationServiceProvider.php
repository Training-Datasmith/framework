<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Database\Console\Migrations\Fresh_Command;
use Illuminate\Database\Console\Migrations\Install_Command;
use Illuminate\Database\Console\Migrations\Migrate_Command;
use Illuminate\Database\Console\Migrations\Migrate_Make_Command;
use Illuminate\Database\Console\Migrations\Refresh_Command;
use Illuminate\Database\Console\Migrations\Reset_Command;
use Illuminate\Database\Console\Migrations\Rollback_Command;
use Illuminate\Database\Console\Migrations\Status_Command;
use Illuminate\Database\Migrations\Database_Migration_Repository;
use Illuminate\Database\Migrations\Migration_Creator;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Service_Provider;
class Migration_Service_Provider extends Service_Provider implements Deferrable_Provider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = ['Migrate' => Migrate_Command::class, 'MigrateFresh' => Fresh_Command::class, 'MigrateInstall' => Install_Command::class, 'MigrateRefresh' => Refresh_Command::class, 'MigrateReset' => Reset_Command::class, 'MigrateRollback' => Rollback_Command::class, 'MigrateStatus' => Status_Command::class, 'MigrateMake' => Migrate_Make_Command::class];
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->register_repository();
        $this->register_migrator();
        $this->register_creator();
        $this->register_commands($this->commands);
    }
    /**
     * Register the migration repository service.
     *
     * @return void
     */
    protected function register_repository()
    {
        $this->app->singleton('migration.repository', function (array $app): \Illuminate\Database\Migrations\Database_Migration_Repository {
            $migrations = $app['config']['database.migrations'];
            $table = is_array($migrations) ? $migrations['table'] ?? null : $migrations;
            return new Database_Migration_Repository($app['db'], $table);
        });
    }
    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function register_migrator()
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton('migrator', function (array $app): \Illuminate\Database\Migrations\Migrator {
            $repository = $app['migration.repository'];
            return new Migrator($repository, $app['db'], $app['files'], $app['events']);
        });
        $this->app->bind(Migrator::class, fn($app) => $app['migrator']);
    }
    /**
     * Register the migration creator.
     *
     * @return void
     */
    protected function register_creator()
    {
        $this->app->singleton('migration.creator', fn($app): \Illuminate\Database\Migrations\Migration_Creator => new Migration_Creator($app['files'], $app->base_path('stubs')));
    }
    /**
     * Register the given commands.
     *
     * @return void
     */
    protected function register_commands(array $commands)
    {
        foreach (array_keys($commands) as $command) {
            $this->{"register{$command}Command"}();
        }
        $this->commands(array_values($commands));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_migrate_command()
    {
        $this->app->singleton(Migrate_Command::class, fn($app): \Illuminate\Database\Console\Migrations\Migrate_Command => new Migrate_Command($app['migrator'], $app[Dispatcher::class]));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_migrate_fresh_command()
    {
        $this->app->singleton(Fresh_Command::class, fn($app): \Illuminate\Database\Console\Migrations\Fresh_Command => new Fresh_Command($app['migrator']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_migrate_install_command()
    {
        $this->app->singleton(Install_Command::class, fn($app): \Illuminate\Database\Console\Migrations\Install_Command => new Install_Command($app['migration.repository']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_migrate_make_command()
    {
        $this->app->singleton(Migrate_Make_Command::class, function (array $app): \Illuminate\Database\Console\Migrations\Migrate_Make_Command {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app['migration.creator'];
            $composer = $app['composer'];
            return new Migrate_Make_Command($creator, $composer);
        });
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_migrate_refresh_command()
    {
        $this->app->singleton(Refresh_Command::class);
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_migrate_reset_command()
    {
        $this->app->singleton(Reset_Command::class, fn($app): \Illuminate\Database\Console\Migrations\Reset_Command => new Reset_Command($app['migrator']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_migrate_rollback_command()
    {
        $this->app->singleton(Rollback_Command::class, fn($app): \Illuminate\Database\Console\Migrations\Rollback_Command => new Rollback_Command($app['migrator']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_migrate_status_command()
    {
        $this->app->singleton(Status_Command::class, fn($app): \Illuminate\Database\Console\Migrations\Status_Command => new Status_Command($app['migrator']));
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return array_merge(['migrator', 'migration.repository', 'migration.creator', Migrator::class], array_values($this->commands));
    }
}