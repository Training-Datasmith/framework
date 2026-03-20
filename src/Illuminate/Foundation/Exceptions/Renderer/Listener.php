<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions\Renderer;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\Query_Executed;
use Illuminate\Queue\Events\Job_Processed;
use Illuminate\Queue\Events\Job_Processing;
use Laravel\Octane\Events\Request_Received;
use Laravel\Octane\Events\Request_Terminated;
use Laravel\Octane\Events\Task_Received;
use Laravel\Octane\Events\Tick_Received;
class Listener
{
    /**
     * The queries that have been executed.
     *
     * @var array<int, array{connectionName: string, time: float, sql: string, bindings: array}>
     */
    protected $queries = [];
    /**
     * Register the appropriate listeners on the given event dispatcher.
     */
    public function register_listeners(Dispatcher $events): void
    {
        $events->listen(Query_Executed::class, $this->on_query_executed(...));
        $events->listen([Job_Processing::class, Job_Processed::class], function (): void {
            $this->queries = [];
        });
        if (isset($_SERVER['LARAVEL_OCTANE'])) {
            $events->listen([Request_Received::class, Task_Received::class, Tick_Received::class, Request_Terminated::class], function (): void {
                $this->queries = [];
            });
        }
    }
    /**
     * Returns the queries that have been executed.
     *
     * @return array<int, array{connectionName: string, time: float, sql: string, bindings: array}>
     */
    public function queries()
    {
        return $this->queries;
    }
    /**
     * Listens for the query executed event.
     */
    public function on_query_executed(Query_Executed $event): void
    {
        if (count($this->queries) === 101) {
            return;
        }
        $this->queries[] = ['connectionName' => $event->connection_name, 'time' => $event->time, 'sql' => $event->sql, 'bindings' => $event->connection->prepare_bindings($event->bindings)];
    }
}