<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Providers;

use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Support\Composer;
use Illuminate\Support\Service_Provider;
class Composer_Service_Provider extends Service_Provider implements Deferrable_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('composer', fn($app): \Illuminate\Support\Composer => new Composer($app['files'], $app->base_path()));
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['composer'];
    }
}