<?php

declare(strict_types=1);

namespace Illuminate\Log;

use Illuminate\Support\ServiceProvider;

class LogServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('log', fn ($app): \Illuminate\Log\LogManager => new LogManager($app));
    }
}
