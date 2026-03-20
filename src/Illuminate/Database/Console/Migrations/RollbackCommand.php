<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Migrations;

use Illuminate\Console\Command;
use Illuminate\Console\Confirmable_Trait;
use Illuminate\Console\Prohibitable;
use Illuminate\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command('migrate:rollback')]
class Rollback_Command extends Base_Command
{
    use Confirmable_Trait;
    use Prohibitable;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:rollback';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback the last database migration';
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
     */
    public function handle(): int
    {
        if ($this->is_prohibited() || !$this->confirm_to_proceed()) {
            return Command::FAILURE;
        }
        $this->migrator->using_connection($this->option('database'), function (): void {
            $this->migrator->set_output($this->output)->rollback($this->get_migration_paths(), ['pretend' => $this->option('pretend'), 'step' => (int) $this->option('step'), 'batch' => (int) $this->option('batch')]);
        });
        return 0;
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['database', null, Input_Option::VALUE_OPTIONAL, 'The database connection to use'], ['force', null, Input_Option::VALUE_NONE, 'Force the operation to run when in production'], ['path', null, Input_Option::VALUE_OPTIONAL | Input_Option::VALUE_IS_ARRAY, 'The path(s) to the migrations files to be executed'], ['realpath', null, Input_Option::VALUE_NONE, 'Indicate any provided migration file paths are pre-resolved absolute paths'], ['pretend', null, Input_Option::VALUE_NONE, 'Dump the SQL queries that would be run'], ['step', null, Input_Option::VALUE_OPTIONAL, 'The number of migrations to be reverted'], ['batch', null, Input_Option::VALUE_REQUIRED, 'The batch of migrations (identified by their batch number) to be reverted']];
    }
}