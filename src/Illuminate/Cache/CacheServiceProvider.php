<?php

declare(strict_types=1);

namespace Illuminate\Cache;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Cache\Adapter\Psr16Adapter;

class CacheServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('cache', fn ($app): \Illuminate\Cache\CacheManager => new CacheManager($app));

        $this->app->singleton('cache.store', fn ($app) => $app['cache']->driver());

        $this->app->singleton('cache.psr6', fn ($app): \Symfony\Component\Cache\Adapter\Psr16Adapter => new Psr16Adapter($app['cache.store']));

        $this->app->singleton('memcached.connector', fn (): \Illuminate\Cache\MemcachedConnector => new MemcachedConnector());

        $this->app->singleton(RateLimiter::class, fn ($app): \Illuminate\Cache\RateLimiter => new RateLimiter($app->make('cache')->driver(
            $app['config']->get('cache.limiter')
        )));
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'cache', 'cache.store', 'cache.psr6', 'memcached.connector', RateLimiter::class,
        ];
    }
}
