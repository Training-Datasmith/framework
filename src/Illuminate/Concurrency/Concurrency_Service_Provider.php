<?php

declare (strict_types=1);
namespace Illuminate\Concurrency;

use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Support\Service_Provider;
class Concurrency_Service_Provider extends Service_Provider implements Deferrable_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(Concurrency_Manager::class, fn($app): \Illuminate\Concurrency\Concurrency_Manager => new Concurrency_Manager($app));
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [Concurrency_Manager::class];
    }
}