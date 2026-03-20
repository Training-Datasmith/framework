<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

trait Database_Transactions
{
    /**
     * Handle database transactions on the specified connections.
     */
    public function begin_database_transaction(): void
    {
        $database = $this->app->make('db');
        $connections = $this->connections_to_transact();
        $this->app->instance('db.transactions', $transactions_manager = new Database_Transactions_Manager($connections));
        foreach ($connections as $name) {
            $connection = $database->connection($name);
            $connection->set_transaction_manager($transactions_manager);
            $dispatcher = $connection->get_event_dispatcher();
            $connection->unset_event_dispatcher();
            $connection->begin_transaction();
            $connection->set_event_dispatcher($dispatcher);
        }
        $this->before_application_destroyed(function () use ($database): void {
            foreach ($this->connections_to_transact() as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->get_event_dispatcher();
                $connection->unset_event_dispatcher();
                $connection->roll_back();
                $connection->set_event_dispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }
    /**
     * The database connections that should have transactions.
     *
     * @return array
     */
    protected function connections_to_transact()
    {
        return property_exists($this, 'connectionsToTransact') ? $this->connections_to_transact : [null];
    }
}