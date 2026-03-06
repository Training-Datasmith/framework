<?php

declare(strict_types=1);

namespace Illuminate\Redis;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class RedisServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('redis', function ($app): \Illuminate\Redis\RedisManager {
            $config = $app->make('config')->get('database.redis', []);

            return new RedisManager($app, Arr::pull($config, 'client', 'phpredis'), $config);
        });

        $this->app->bind('redis.connection', fn ($app) => $app['redis']->connection());
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['redis', 'redis.connection'];
    }
}
