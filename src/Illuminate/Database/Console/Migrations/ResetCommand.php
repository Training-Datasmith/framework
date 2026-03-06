<?php

declare(strict_types=1);

namespace Illuminate\Database\Console\Migrations;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Console\Prohibitable;
use Illuminate\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'migrate:reset')]
class ResetCommand extends BaseCommand
{
    use ConfirmableTrait;
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
    public function __construct(/**
     * The migrator instance.
     */
        protected \Illuminate\Database\Migrations\Migrator $migrator
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->isProhibited() ||
            ! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        return $this->migrator->usingConnection($this->option('database'), function () {
            // First, we'll make sure that the migration table actually exists before we
            // start trying to rollback and re-run all of the migrations. If it's not
            // present we'll just bail out with an info message for the developers.
            if (! $this->migrator->repositoryExists()) {
                return $this->components->warn('Migration table not found.');
            }

            $this->migrator->setOutput($this->output)->reset(
                $this->getMigrationPaths(),
                $this->option('pretend')
            );
        });
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use'],

            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production'],

            ['path', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The path(s) to the migrations files to be executed'],

            ['realpath', null, InputOption::VALUE_NONE, 'Indicate any provided migration file paths are pre-resolved absolute paths'],

            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run'],
        ];
    }
}
