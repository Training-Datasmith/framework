<?php

declare(strict_types=1);

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
        $this->app->singleton('composer', fn ($app): \Illuminate\Support\Composer => new Composer($app['files'], $app->basePath()));
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['composer'];
    }
}
