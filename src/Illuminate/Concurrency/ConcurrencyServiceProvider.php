<?php

namespace Illuminate\Concurrency;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ConcurrencyServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(ConcurrencyManager::class, fn($app) => new ConcurrencyManager($app));
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ConcurrencyManager::class,
        ];
    }
}
