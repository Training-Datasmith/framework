<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Migrations;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'migrate:status')]
class Status_Command extends Base_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:status';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the status of each migration';
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
     * @return int|null
     */
    public function handle()
    {
        return $this->migrator->using_connection($this->option('database'), function () {
            if (!$this->migrator->repository_exists()) {
                $this->components->error('Migration table not found.');
                return 1;
            }
            $ran = $this->migrator->get_repository()->get_ran();
            $batches = $this->migrator->get_repository()->get_migration_batches();
            $migrations = $this->get_status_for($ran, $batches)->when($this->option('pending') !== false, fn($collection): \Illuminate\Support\Collection => $collection->filter(fn($migration) => (new Stringable($migration[1]))->contains('Pending')));
            if (count($migrations) > 0) {
                $this->new_line();
                $this->components->two_column_detail('<fg=gray>Migration name</>', '<fg=gray>Batch / Status</>');
                $migrations->each(fn($migration) => $this->components->two_column_detail($migration[0], $migration[1]));
                $this->new_line();
            } elseif ($this->option('pending') !== false) {
                $this->components->info('No pending migrations');
            } else {
                $this->components->info('No migrations found');
            }
            if ($this->option('pending') && $migrations->some(fn($m) => (new Stringable($m[1]))->contains('Pending'))) {
                return $this->option('pending');
            }
        });
    }
    /**
     * Get the status for the given run migrations.
     */
    protected function get_status_for(array $ran, array $batches): \Illuminate\Support\Collection
    {
        return (new Collection($this->get_all_migration_files()))->map(function ($migration) use ($ran, $batches): array {
            $migration_name = $this->migrator->get_migration_name($migration);
            $status = in_array($migration_name, $ran) ? '<fg=green;options=bold>Ran</>' : '<fg=yellow;options=bold>Pending</>';
            if (in_array($migration_name, $ran)) {
                $status = '[' . $batches[$migration_name] . '] ' . $status;
            }
            return [$migration_name, $status];
        });
    }
    /**
     * Get an array of all of the migration files.
     *
     * @return array
     */
    protected function get_all_migration_files()
    {
        return $this->migrator->get_migration_files($this->get_migration_paths());
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['database', null, Input_Option::VALUE_OPTIONAL, 'The database connection to use'], ['pending', null, Input_Option::VALUE_OPTIONAL, 'Only list pending migrations', false], ['path', null, Input_Option::VALUE_OPTIONAL | Input_Option::VALUE_IS_ARRAY, 'The path(s) to the migrations files to use'], ['realpath', null, Input_Option::VALUE_NONE, 'Indicate any provided migration file paths are pre-resolved absolute paths']];
    }
}