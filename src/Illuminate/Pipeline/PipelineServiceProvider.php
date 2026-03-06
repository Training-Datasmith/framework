<?php

namespace Illuminate\Pipeline;

use Illuminate\Contracts\Pipeline\Hub as PipelineHubContract;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class PipelineServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(
            PipelineHubContract::class,
            Hub::class
        );

        $this->app->bind('pipeline', fn ($app): \Illuminate\Pipeline\Pipeline => new Pipeline($app));
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            PipelineHubContract::class,
            'pipeline',
        ];
    }
}
