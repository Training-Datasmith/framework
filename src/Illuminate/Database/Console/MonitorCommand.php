<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\Database_Busy;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'db:monitor')]
class Monitor_Command extends Database_Inspection_Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:monitor
                {--databases= : The database connections to monitor}
                {--max= : The maximum number of connections that can be open before an event is dispatched}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor the number of connections on the specified database';
    /**
     * Create a new command instance.
     */
    public function __construct(
        /**
         * The connection resolver instance.
         */
        protected \Illuminate\Database\Connection_Resolver_Interface $connection,
        /**
         * The events dispatcher instance.
         */
        protected \Illuminate\Contracts\Events\Dispatcher $events
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $databases = $this->parse_databases($this->option('databases'));
        $this->display_connections($databases);
        if ($this->option('max')) {
            $this->dispatch_events($databases);
        }
    }
    /**
     * Parse the database into an array of the connections.
     *
     * @param  string  $databases
     */
    protected function parse_databases($databases): \Illuminate\Support\Collection
    {
        return (new Collection(explode(',', $databases)))->map(function ($database): array {
            if (!$database) {
                $database = $this->laravel['config']['database.default'];
            }
            $max_connections = $this->option('max');
            $connections = $this->connection->connection($database)->thread_count();
            return ['database' => $database, 'connections' => $connections, 'status' => $max_connections && $connections >= $max_connections ? '<fg=yellow;options=bold>ALERT</>' : '<fg=green;options=bold>OK</>'];
        });
    }
    /**
     * Display the databases and their connection counts in the console.
     *
     * @param  \Illuminate\Support\Collection  $databases
     * @return void
     */
    protected function display_connections($databases)
    {
        $this->new_line();
        $this->components->two_column_detail('<fg=gray>Database name</>', '<fg=gray>Connections</>');
        $databases->each(function (array $database): void {
            $status = '[' . $database['connections'] . '] ' . $database['status'];
            $this->components->two_column_detail($database['database'], $status);
        });
        $this->new_line();
    }
    /**
     * Dispatch the database monitoring events.
     *
     * @param  \Illuminate\Support\Collection  $databases
     * @return void
     */
    protected function dispatch_events($databases)
    {
        $databases->each(function (array $database): void {
            if ($database['status'] === '<fg=green;options=bold>OK</>') {
                return;
            }
            $this->events->dispatch(new Database_Busy($database['database'], $database['connections']));
        });
    }
}