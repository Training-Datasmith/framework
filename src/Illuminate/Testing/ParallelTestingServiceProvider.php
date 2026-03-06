<?php

declare(strict_types=1);

namespace Illuminate\Testing;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\Concerns\TestCaches;
use Illuminate\Testing\Concerns\TestDatabases;
use Illuminate\Testing\Concerns\TestViews;

class ParallelTestingServiceProvider extends ServiceProvider implements DeferrableProvider
{
    use TestCaches;
    use TestDatabases;
    use TestViews;

    /**
     * Boot the application's service providers.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->bootTestCache();
            $this->bootTestDatabase();
            $this->bootTestViews();
        }
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(ParallelTesting::class, fn (): \Illuminate\Testing\ParallelTesting => new ParallelTesting($this->app));
        }
    }
}
