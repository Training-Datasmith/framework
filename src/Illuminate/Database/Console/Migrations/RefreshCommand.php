<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Migrations;

use Illuminate\Console\Command;
use Illuminate\Console\Confirmable_Trait;
use Illuminate\Console\Prohibitable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\Database_Refreshed;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'migrate:refresh')]
class Refresh_Command extends Command
{
    use Confirmable_Trait;
    use Prohibitable;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:refresh';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset and re-run all migrations';
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->is_prohibited() || !$this->confirm_to_proceed()) {
            return Command::FAILURE;
        }
        // Next we'll gather some of the options so that we can have the right options
        // to pass to the commands. This includes options such as which database to
        // use and the path to use for the migration. Then we'll run the command.
        $database = $this->input->get_option('database');
        $path = $this->input->get_option('path');
        // If the "step" option is specified it means we only want to rollback a small
        // number of migrations before migrating again. For example, the user might
        // only rollback and remigrate the latest four migrations instead of all.
        $step = $this->input->get_option('step') ?: 0;
        if ($step > 0) {
            $this->run_rollback($database, $path, $step);
        } else {
            $this->run_reset($database, $path);
        }
        // The refresh command is essentially just a brief aggregate of a few other of
        // the migration commands and just provides a convenient wrapper to execute
        // them in succession. We'll also see if we need to re-seed the database.
        $this->call('migrate', array_filter(['--database' => $database, '--path' => $path, '--realpath' => $this->input->get_option('realpath'), '--force' => true]));
        if ($this->laravel->bound(Dispatcher::class)) {
            $this->laravel[Dispatcher::class]->dispatch(new Database_Refreshed($database, $this->needs_seeding()));
        }
        if ($this->needs_seeding()) {
            $this->run_seeder($database);
        }
        return 0;
    }
    /**
     * Run the rollback command.
     *
     * @param  string  $database
     * @param  string  $path
     * @param  int  $step
     * @return void
     */
    protected function run_rollback($database, $path, $step)
    {
        $this->call('migrate:rollback', array_filter(['--database' => $database, '--path' => $path, '--realpath' => $this->input->get_option('realpath'), '--step' => $step, '--force' => true]));
    }
    /**
     * Run the reset command.
     *
     * @param  string  $database
     * @param  string  $path
     * @return void
     */
    protected function run_reset($database, $path)
    {
        $this->call('migrate:reset', array_filter(['--database' => $database, '--path' => $path, '--realpath' => $this->input->get_option('realpath'), '--force' => true]));
    }
    /**
     * Determine if the developer has requested database seeding.
     */
    protected function needs_seeding(): bool
    {
        if ($this->option('seed')) {
            return true;
        }
        return (bool) $this->option('seeder');
    }
    /**
     * Run the database seeder command.
     *
     * @param  string  $database
     * @return void
     */
    protected function run_seeder($database)
    {
        $this->call('db:seed', array_filter(['--database' => $database, '--class' => $this->option('seeder') ?: 'Database\Seeders\DatabaseSeeder', '--force' => true]));
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['database', null, Input_Option::VALUE_OPTIONAL, 'The database connection to use'], ['force', null, Input_Option::VALUE_NONE, 'Force the operation to run when in production'], ['path', null, Input_Option::VALUE_OPTIONAL | Input_Option::VALUE_IS_ARRAY, 'The path(s) to the migrations files to be executed'], ['realpath', null, Input_Option::VALUE_NONE, 'Indicate any provided migration file paths are pre-resolved absolute paths'], ['seed', null, Input_Option::VALUE_NONE, 'Indicates if the seed task should be re-run'], ['seeder', null, Input_Option::VALUE_OPTIONAL, 'The class name of the root seeder'], ['step', null, Input_Option::VALUE_OPTIONAL, 'The number of migrations to be reverted & re-run']];
    }
}