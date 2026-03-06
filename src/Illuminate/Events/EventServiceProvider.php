<?php

declare(strict_types=1);

namespace Illuminate\Events;

use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('events', fn ($app): \Illuminate\Events\Dispatcher => (new Dispatcher($app))->setQueueResolver(fn () => app(QueueFactoryContract::class))->setTransactionManagerResolver(fn () => app()->bound('db.transactions')
            ? app('db.transactions')
            : null));
    }
}
