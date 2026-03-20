<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Migrations;

use Illuminate\Console\Command;
use Illuminate\Console\Confirmable_Trait;
use Illuminate\Console\Prohibitable;
use Illuminate\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'migrate:reset')]
class Reset_Command extends Base_Command
{
    use Confirmable_Trait;
    use Prohibitable;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:reset';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback all database migrations';
    /**
     * Create a new migration rollback command instance.
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
     *
     * @return int
     */
    public function handle()
    {
        if ($this->is_prohibited() || !$this->confirm_to_proceed()) {
            return Command::FAILURE;
        }
        return $this->migrator->using_connection($this->option('database'), function () {
            // First, we'll make sure that the migration table actually exists before we
            // start trying to rollback and re-run all of the migrations. If it's not
            // present we'll just bail out with an info message for the developers.
            if (!$this->migrator->repository_exists()) {
                return $this->components->warn('Migration table not found.');
            }
            $this->migrator->set_output($this->output)->reset($this->get_migration_paths(), $this->option('pretend'));
        });
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['database', null, Input_Option::VALUE_OPTIONAL, 'The database connection to use'], ['force', null, Input_Option::VALUE_NONE, 'Force the operation to run when in production'], ['path', null, Input_Option::VALUE_OPTIONAL | Input_Option::VALUE_IS_ARRAY, 'The path(s) to the migrations files to be executed'], ['realpath', null, Input_Option::VALUE_NONE, 'Indicate any provided migration file paths are pre-resolved absolute paths'], ['pretend', null, Input_Option::VALUE_NONE, 'Dump the SQL queries that would be run']];
    }
}