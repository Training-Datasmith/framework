<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Support\Service_Provider;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
class Cache_Service_Provider extends Service_Provider implements Deferrable_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('cache', fn($app): \Illuminate\Cache\Cache_Manager => new Cache_Manager($app));
        $this->app->singleton('cache.store', fn($app) => $app['cache']->driver());
        $this->app->singleton('cache.psr6', fn($app): \Symfony\Component\Cache\Adapter\Psr16Adapter => new Psr16Adapter($app['cache.store']));
        $this->app->singleton('memcached.connector', fn(): \Illuminate\Cache\Memcached_Connector => new Memcached_Connector());
        $this->app->singleton(Rate_Limiter::class, fn($app): \Illuminate\Cache\Rate_Limiter => new Rate_Limiter($app->make('cache')->driver($app['config']->get('cache.limiter'))));
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['cache', 'cache.store', 'cache.psr6', 'memcached.connector', Rate_Limiter::class];
    }
}