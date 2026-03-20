<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Migrations;

use Illuminate\Console\Command;
use Illuminate\Console\Confirmable_Trait;
use Illuminate\Console\Prohibitable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\Database_Refreshed;
use Illuminate\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'migrate:fresh')]
class Fresh_Command extends Command
{
    use Confirmable_Trait;
    use Prohibitable;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:fresh';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all tables and re-run all migrations';
    /**
     * Create a new fresh command instance.
     */
    public function __construct(
        /**
         * The migrator instance.
         */
        protected \Illuminate\Database\Migrations\Migrator $migrator
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->is_prohibited() || !$this->confirm_to_proceed()) {
            return Command::FAILURE;
        }
        $database = $this->input->get_option('database');
        $this->migrator->using_connection($database, function () use ($database): void {
            if ($this->migrator->repository_exists()) {
                $this->new_line();
                $this->components->task('Dropping all tables', fn(): bool => $this->call_silent('db:wipe', array_filter(['--database' => $database, '--drop-views' => $this->option('drop-views'), '--drop-types' => $this->option('drop-types'), '--force' => true])) == 0);
            }
        });
        $this->new_line();
        $this->call('migrate', array_filter(['--database' => $database, '--path' => $this->input->get_option('path'), '--realpath' => $this->input->get_option('realpath'), '--schema-path' => $this->input->get_option('schema-path'), '--force' => true, '--step' => $this->option('step')]));
        if ($this->laravel->bound(Dispatcher::class)) {
            $this->laravel[Dispatcher::class]->dispatch(new Database_Refreshed($database, $this->needs_seeding()));
        }
        if ($this->needs_seeding()) {
            $this->run_seeder($database);
        }
        return 0;
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
        return [['database', null, Input_Option::VALUE_OPTIONAL, 'The database connection to use'], ['drop-views', null, Input_Option::VALUE_NONE, 'Drop all tables and views'], ['drop-types', null, Input_Option::VALUE_NONE, 'Drop all tables and types (Postgres only)'], ['force', null, Input_Option::VALUE_NONE, 'Force the operation to run when in production'], ['path', null, Input_Option::VALUE_OPTIONAL | Input_Option::VALUE_IS_ARRAY, 'The path(s) to the migrations files to be executed'], ['realpath', null, Input_Option::VALUE_NONE, 'Indicate any provided migration file paths are pre-resolved absolute paths'], ['schema-path', null, Input_Option::VALUE_OPTIONAL, 'The path to a schema dump file'], ['seed', null, Input_Option::VALUE_NONE, 'Indicates if the seed task should be re-run'], ['seeder', null, Input_Option::VALUE_OPTIONAL, 'The class name of the root seeder'], ['step', null, Input_Option::VALUE_NONE, 'Force the migrations to be run so they can be rolled back individually']];
    }
}