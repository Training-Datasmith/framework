<?php

namespace Illuminate\Hashing;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class HashServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('hash', fn($app) => new HashManager($app));

        $this->app->singleton('hash.driver', fn($app) => $app['hash']->driver());
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['hash', 'hash.driver'];
    }
}
