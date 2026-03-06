<?php

namespace Illuminate\Foundation\Testing\Concerns;

use Exception;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Foundation\Application;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Env;

trait InteractsWithRedis
{
    /**
     * Indicate connection failed if redis is not available.
     *
     * @var bool
     */
    private static $connectionFailedOnceWithDefaultsSkip = false;

    /**
     * Redis manager instance.
     *
     * @var array<string, \Illuminate\Redis\RedisManager>
     */
    private $redis;

    /**
     * Setup redis connection.
     */
    public function setUpRedis(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.self::class);
        }

        if (static::$connectionFailedOnceWithDefaultsSkip) {
            $this->markTestSkipped('Trying default host/port failed, please set environment variable REDIS_HOST & REDIS_PORT to enable '.self::class);
        }

        $app = $this->app ?? new Application;
        $host = Env::get('REDIS_HOST', '127.0.0.1');
        $port = Env::get('REDIS_PORT', 6379);

        foreach (static::redisDriverProvider() as $driver) {
            if (Env::get('REDIS_CLUSTER_HOSTS_AND_PORTS')) {
                $config = [
                    'options' => [
                        'cluster' => 'redis',
                        'prefix' => 'test_',
                    ],
                    'clusters' => [
                        'default' => array_map(
                            static fn ($hostAndPort): array => [
                                'host' => explode(':', $hostAndPort)[0],
                                'port' => explode(':', $hostAndPort)[1],
                            ],
                            explode(',', (string) Env::get('REDIS_CLUSTER_HOSTS_AND_PORTS')),
                        ),
                    ],
                ];
            } else {
                $config = [
                    'options' => [
                        'prefix' => 'test_',
                    ],
                    'default' => [
                        'host' => $host,
                        'port' => $port,
                        'database' => 5,
                        'timeout' => 0.5,
                        'name' => 'default',
                    ],
                    'cache' => [
                        'host' => $host,
                        'port' => $port,
                        'database' => 6,
                        'timeout' => 0.5,
                    ],
                ];
            }
            $this->redis[$driver[0]] = new RedisManager($app, $driver[0], $config);
        }

        $defaultDriver = Env::get('REDIS_CLIENT', 'phpredis');

        try {
            $this->redis[$defaultDriver]->connection()->flushdb();
        } catch (Exception) {
            if ($host === '127.0.0.1' && $port === 6379 && Env::get('REDIS_HOST') === null) {
                static::$connectionFailedOnceWithDefaultsSkip = true;

                $this->markTestSkipped('Trying default host/port failed, please set environment variable REDIS_HOST & REDIS_PORT to enable '.self::class);
            }
        }

        $app->instance('redis', $this->redis[$defaultDriver]);
    }

    /**
     * Teardown redis connection.
     */
    public function tearDownRedis(): void
    {
        if (static::$connectionFailedOnceWithDefaultsSkip === true) {
            return;
        }

        if (isset($this->redis['phpredis'])) {
            $this->redis['phpredis']->connection()->flushdb();
        }

        foreach (static::redisDriverProvider() as $driver) {
            if (isset($this->redis[$driver[0]])) {
                $this->redis[$driver[0]]->connection()->disconnect();
            }
        }
    }

    /**
     * Get redis driver provider.
     */
    public static function redisDriverProvider(): array
    {
        return [
            ['predis'],
            ['phpredis'],
        ];
    }

    /**
     * Run test if redis is available.
     *
     * @param  callable  $callback
     */
    public function ifRedisAvailable($callback): void
    {
        $this->setUpRedis();

        $callback();

        $this->tearDownRedis();
    }
}
