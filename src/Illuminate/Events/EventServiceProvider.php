<?php

declare (strict_types=1);
namespace Illuminate\Events;

use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Support\Service_Provider;
class Event_Service_Provider extends Service_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('events', fn($app): \Illuminate\Events\Dispatcher => (new Dispatcher($app))->set_queue_resolver(fn() => app(Queue_Factory_Contract::class))->set_transaction_manager_resolver(fn() => app()->bound('db.transactions') ? app('db.transactions') : null));
    }
}