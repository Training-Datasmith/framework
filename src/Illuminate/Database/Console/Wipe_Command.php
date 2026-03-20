<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Confirmable_Trait;
use Illuminate\Console\Prohibitable;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'db:wipe')]
class Wipe_Command extends Command
{
    use Confirmable_Trait;
    use Prohibitable;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'db:wipe';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all tables, views, and types';
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->is_prohibited() || !$this->confirm_to_proceed()) {
            return Command::FAILURE;
        }
        $database = $this->input->get_option('database');
        if ($this->option('drop-views')) {
            $this->drop_all_views($database);
            $this->components->info('Dropped all views successfully.');
        }
        $this->drop_all_tables($database);
        $this->components->info('Dropped all tables successfully.');
        if ($this->option('drop-types')) {
            $this->drop_all_types($database);
            $this->components->info('Dropped all types successfully.');
        }
        $this->flush_database_connection($database);
        return 0;
    }
    /**
     * Drop all of the database tables.
     *
     * @param  string  $database
     * @return void
     */
    protected function drop_all_tables($database)
    {
        $this->laravel['db']->connection($database)->get_schema_builder()->drop_all_tables();
    }
    /**
     * Drop all of the database views.
     *
     * @param  string  $database
     * @return void
     */
    protected function drop_all_views($database)
    {
        $this->laravel['db']->connection($database)->get_schema_builder()->drop_all_views();
    }
    /**
     * Drop all of the database types.
     *
     * @param  string  $database
     * @return void
     */
    protected function drop_all_types($database)
    {
        $this->laravel['db']->connection($database)->get_schema_builder()->drop_all_types();
    }
    /**
     * Flush the given database connection.
     *
     * @param  string  $database
     * @return void
     */
    protected function flush_database_connection($database)
    {
        $this->laravel['db']->connection($database)->disconnect();
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['database', null, Input_Option::VALUE_OPTIONAL, 'The database connection to use'], ['drop-views', null, Input_Option::VALUE_NONE, 'Drop all tables and views'], ['drop-types', null, Input_Option::VALUE_NONE, 'Drop all tables and types (Postgres only)'], ['force', null, Input_Option::VALUE_NONE, 'Force the operation to run when in production']];
    }
}