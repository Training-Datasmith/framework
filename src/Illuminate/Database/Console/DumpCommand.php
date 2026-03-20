<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Prohibitable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Connection_Resolver_Interface;
use Illuminate\Database\Events\Migrations_Pruned;
use Illuminate\Database\Events\Schema_Dumped;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'schema:dump')]
class Dump_Command extends Command
{
    use Prohibitable;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'schema:dump
                {--database= : The database connection to use}
                {--path= : The path where the schema dump file should be stored}
                {--prune : Delete all existing migration files}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump the given database schema';
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(Connection_Resolver_Interface $connections, Dispatcher $dispatcher)
    {
        if ($this->is_prohibited()) {
            return Command::FAILURE;
        }
        $connection = $connections->connection($database = $this->input->get_option('database'));
        $this->schema_state($connection)->dump($connection, $path = $this->path($connection));
        $dispatcher->dispatch(new Schema_Dumped($connection, $path));
        $info = 'Database schema dumped';
        if ($this->option('prune')) {
            (new Filesystem())->delete_directory($path = database_path('migrations'), preserve: false);
            $info .= ' and pruned';
            $dispatcher->dispatch(new Migrations_Pruned($connection, $path));
        }
        $this->components->info($info . ' successfully.');
    }
    /**
     * Create a schema state instance for the given connection.
     *
     * @return mixed
     */
    protected function schema_state(Connection $connection)
    {
        $migrations = Config::get('database.migrations', 'migrations');
        $migration_table = is_array($migrations) ? $migrations['table'] ?? 'migrations' : $migrations;
        return $connection->get_schema_state()->with_migration_table($migration_table)->handle_output_using(function ($type, string|iterable $buffer): void {
            $this->output->write($buffer);
        });
    }
    /**
     * Get the path that the dump should be written to.
     */
    protected function path(Connection $connection)
    {
        return tap($this->option('path') ?: database_path('schema/' . $connection->get_name() . '-schema.sql'), function ($path): void {
            (new Filesystem())->ensure_directory_exists(dirname($path));
        });
    }
}