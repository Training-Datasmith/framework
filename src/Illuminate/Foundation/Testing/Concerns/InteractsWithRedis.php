<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Exception;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Foundation\Application;
use Illuminate\Redis\Redis_Manager;
use Illuminate\Support\Env;
trait Interacts_With_Redis
{
    /**
     * Indicate connection failed if redis is not available.
     *
     * @var bool
     */
    private static $connection_failed_once_with_defaults_skip = false;
    /**
     * Redis manager instance.
     *
     * @var array<string, \Illuminate\Redis\RedisManager>
     */
    private $redis;
    /**
     * Setup redis connection.
     */
    public function set_up_redis(): void
    {
        if (!extension_loaded('redis')) {
            $this->mark_test_skipped('The redis extension is not installed. Please install the extension to enable ' . self::class);
        }
        if (static::$connection_failed_once_with_defaults_skip) {
            $this->mark_test_skipped('Trying default host/port failed, please set environment variable REDIS_HOST & REDIS_PORT to enable ' . self::class);
        }
        $app = $this->app ?? new Application();
        $host = Env::get('REDIS_HOST', '127.0.0.1');
        $port = Env::get('REDIS_PORT', 6379);
        foreach (static::redis_driver_provider() as $driver) {
            if (Env::get('REDIS_CLUSTER_HOSTS_AND_PORTS')) {
                $config = ['options' => ['cluster' => 'redis', 'prefix' => 'test_'], 'clusters' => ['default' => array_map(static fn($host_and_port): array => ['host' => explode(':', $host_and_port)[0], 'port' => explode(':', $host_and_port)[1]], explode(',', (string) Env::get('REDIS_CLUSTER_HOSTS_AND_PORTS')))]];
            } else {
                $config = ['options' => ['prefix' => 'test_'], 'default' => ['host' => $host, 'port' => $port, 'database' => 5, 'timeout' => 0.5, 'name' => 'default'], 'cache' => ['host' => $host, 'port' => $port, 'database' => 6, 'timeout' => 0.5]];
            }
            $this->redis[$driver[0]] = new Redis_Manager($app, $driver[0], $config);
        }
        $default_driver = Env::get('REDIS_CLIENT', 'phpredis');
        try {
            $this->redis[$default_driver]->connection()->flushdb();
        } catch (Exception) {
            if ($host === '127.0.0.1' && $port === 6379 && Env::get('REDIS_HOST') === null) {
                static::$connection_failed_once_with_defaults_skip = true;
                $this->mark_test_skipped('Trying default host/port failed, please set environment variable REDIS_HOST & REDIS_PORT to enable ' . self::class);
            }
        }
        $app->instance('redis', $this->redis[$default_driver]);
    }
    /**
     * Teardown redis connection.
     */
    public function tear_down_redis(): void
    {
        if (static::$connection_failed_once_with_defaults_skip === true) {
            return;
        }
        if (isset($this->redis['phpredis'])) {
            $this->redis['phpredis']->connection()->flushdb();
        }
        foreach (static::redis_driver_provider() as $driver) {
            if (isset($this->redis[$driver[0]])) {
                $this->redis[$driver[0]]->connection()->disconnect();
            }
        }
    }
    /**
     * Get redis driver provider.
     */
    public static function redis_driver_provider(): array
    {
        return [['predis'], ['phpredis']];
    }
    /**
     * Run test if redis is available.
     *
     * @param  callable  $callback
     */
    public function if_redis_available($callback): void
    {
        $this->set_up_redis();
        $callback();
        $this->tear_down_redis();
    }
}