<?php

declare(strict_types=1);

namespace Illuminate\Broadcasting;

use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(BroadcastManager::class, fn ($app): \Illuminate\Broadcasting\BroadcastManager => new BroadcastManager($app));

        $this->app->singleton(BroadcasterContract::class, fn ($app) => $app->make(BroadcastManager::class)->connection());

        $this->app->alias(
            BroadcastManager::class,
            BroadcastingFactory::class
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            BroadcastManager::class,
            BroadcastingFactory::class,
            BroadcasterContract::class,
        ];
    }
}
