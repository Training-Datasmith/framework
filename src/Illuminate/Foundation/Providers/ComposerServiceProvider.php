<?php

namespace Illuminate\Foundation\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Composer;
use Illuminate\Support\ServiceProvider;

class ComposerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('composer', fn($app) => new Composer($app['files'], $app->basePath()));
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['composer'];
    }
}
