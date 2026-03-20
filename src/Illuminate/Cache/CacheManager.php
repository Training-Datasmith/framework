<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Aws\Dynamo_Db\Dynamo_Db_Client;
use Closure;
use Illuminate\Contracts\Cache\Factory as FactoryContract;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Mockery;
use Mockery\Legacy_Mock_Interface;
/**
 * @mixin \Illuminate\Cache\Repository
 * @mixin \Illuminate\Contracts\Cache\LockProvider
 */
class Cache_Manager implements Factory_Contract
{
    /**
     * The array of resolved cache stores.
     *
     * @var array
     */
    protected $stores = [];
    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $custom_creators = [];
    /**
     * Create a new Cache manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct(
        /**
         * The application instance.
         */
        protected $app
    )
    {
    }
    /**
     * Get a cache store instance by name, wrapped in a repository.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function store($name = null)
    {
        $name ??= $this->get_default_driver();
        return $this->stores[$name] ??= $this->resolve($name);
    }
    /**
     * Get a cache driver instance.
     *
     * @param  string|null  $driver
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function driver($driver = null)
    {
        return $this->store($driver);
    }
    /**
     * Get a memoized cache driver instance.
     *
     * @param  string|null  $driver
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function memo($driver = null)
    {
        $driver ??= $this->get_default_driver();
        $binding_key = "cache.__memoized:{$driver}";
        $is_spy = isset($this->app['cache']) && $this->app['cache'] instanceof Legacy_Mock_Interface;
        $this->app->scoped_if($binding_key, function () use ($driver, $is_spy) {
            $repository = $this->repository(new Memoized_Store($driver, $this->store($driver)), ['events' => false]);
            return $is_spy ? Mockery::spy($repository) : $repository;
        });
        return $this->app->make($binding_key);
    }
    /**
     * Resolve the given store.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Cache\Repository
     *
     * @throws \InvalidArgumentException
     */
    public function resolve($name)
    {
        $config = $this->get_config($name);
        if (is_null($config)) {
            throw new InvalidArgumentException("Cache store [{$name}] is not defined.");
        }
        $config = Arr::add($config, 'store', $name);
        return $this->build($config);
    }
    /**
     * Build a cache repository with the given configuration.
     *
     * @return \Illuminate\Cache\Repository
     * @throws \InvalidArgumentException
     */
    public function build(array $config)
    {
        $config = Arr::add($config, 'store', $config['name'] ?? 'ondemand');
        if (isset($this->custom_creators[$config['driver']])) {
            return $this->call_custom_creator($config);
        }
        $driver_method = 'create' . ucfirst((string) $config['driver']) . 'Driver';
        if (method_exists($this, $driver_method)) {
            return $this->{$driver_method}($config);
        }
        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }
    /**
     * Call a custom driver creator.
     *
     * @return mixed
     */
    protected function call_custom_creator(array $config)
    {
        return $this->custom_creators[$config['driver']]($this->app, $config);
    }
    /**
     * Create an instance of the APC cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_apc_driver(array $config)
    {
        $prefix = $this->get_prefix($config);
        return $this->repository(new Apc_Store(new Apc_Wrapper(), $prefix), $config);
    }
    /**
     * Create an instance of the array cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_array_driver(array $config)
    {
        return $this->repository(new Array_Store($config['serialize'] ?? false, $this->get_serializable_classes($config)), $config);
    }
    /**
     * Create an instance of the database cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_database_driver(array $config)
    {
        $connection = $this->app['db']->connection($config['connection'] ?? null);
        $store = new Database_Store($connection, $config['table'], $this->get_prefix($config), $config['lock_table'] ?? 'cache_locks', $config['lock_lottery'] ?? [2, 100], $config['lock_timeout'] ?? 86400, $this->get_serializable_classes($config));
        return $this->repository($store->set_lock_connection($this->app['db']->connection($config['lock_connection'] ?? $config['connection'] ?? null)), $config);
    }
    /**
     * Create an instance of the DynamoDB cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_dynamodb_driver(array $config)
    {
        $client = $this->new_dynamodb_client($config);
        return $this->repository(new Dynamo_Db_Store($client, $config['table'], $config['attributes']['key'] ?? 'key', $config['attributes']['value'] ?? 'value', $config['attributes']['expiration'] ?? 'expires_at', $this->get_prefix($config), $this->get_serializable_classes($config)), $config);
    }
    /**
     * Create new DynamoDb Client instance.
     *
     * @return \Aws\DynamoDb\DynamoDbClient
     */
    protected function new_dynamodb_client(array $config)
    {
        $dynamo_config = ['region' => $config['region'], 'version' => 'latest', 'endpoint' => $config['endpoint'] ?? null];
        if (!empty($config['key']) && !empty($config['secret'])) {
            $dynamo_config['credentials'] = Arr::only($config, ['key', 'secret']);
            if (!empty($config['token'])) {
                $dynamo_config['credentials']['token'] = $config['token'];
            }
        }
        return new Dynamo_Db_Client($dynamo_config);
    }
    /**
     * Create an instance of the failover cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_failover_driver(array $config)
    {
        return $this->repository(new Failover_Store($this, $this->app->make(Dispatcher_Contract::class), $config['stores']), ['events' => false, ...$config]);
    }
    /**
     * Create an instance of the file cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_file_driver(array $config)
    {
        return $this->repository((new File_Store($this->app['files'], $config['path'], $config['permission'] ?? null, $this->get_serializable_classes($config)))->set_lock_directory($config['lock_path'] ?? null), $config);
    }
    /**
     * Create an instance of the Memcached cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_memcached_driver(array $config)
    {
        $prefix = $this->get_prefix($config);
        $memcached = $this->app['memcached.connector']->connect($config['servers'], $config['persistent_id'] ?? null, $config['options'] ?? [], array_filter($config['sasl'] ?? []));
        return $this->repository(new Memcached_Store($memcached, $prefix), $config);
    }
    /**
     * Create an instance of the Null cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_null_driver()
    {
        return $this->repository(new Null_Store(), []);
    }
    /**
     * Create an instance of the Redis cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_redis_driver(array $config)
    {
        $redis = $this->app['redis'];
        $connection = $config['connection'] ?? 'default';
        $store = new Redis_Store($redis, $this->get_prefix($config), $connection, $this->get_serializable_classes($config));
        return $this->repository($store->set_lock_connection($config['lock_connection'] ?? $connection), $config);
    }
    /**
     * Create an instance of the session cache driver.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function create_session_driver(array $config)
    {
        return $this->repository(new Session_Store($this->get_session(), $config['key'] ?? '_cache'), $config);
    }
    /**
     * Get the session store implementation.
     *
     * @return \Illuminate\Contracts\Session\Session
     *
     * @throws \InvalidArgumentException
     */
    protected function get_session()
    {
        $session = $this->app['session'] ?? null;
        if (!$session) {
            throw new InvalidArgumentException('Session store requires session manager to be available in container.');
        }
        return $session;
    }
    /**
     * Create a new cache repository with the given implementation.
     *
     * @return \Illuminate\Cache\Repository
     */
    public function repository(Store $store, array $config = [])
    {
        return tap(new Repository($store, Arr::only($config, ['store'])), function (\Illuminate\Cache\Repository $repository) use ($config): void {
            if ($config['events'] ?? true) {
                $this->set_event_dispatcher($repository);
            }
        });
    }
    /**
     * Set the event dispatcher on the given repository instance.
     *
     * @return void
     */
    protected function set_event_dispatcher(Repository $repository)
    {
        if (!$this->app->bound(Dispatcher_Contract::class)) {
            return;
        }
        $repository->set_event_dispatcher($this->app[Dispatcher_Contract::class]);
    }
    /**
     * Re-set the event dispatcher on all resolved cache repositories.
     */
    public function refresh_event_dispatcher(): void
    {
        array_map($this->set_event_dispatcher(...), $this->stores);
    }
    /**
     * Get the cache prefix.
     *
     * @return string
     */
    protected function get_prefix(array $config)
    {
        return $config['prefix'] ?? $this->app['config']['cache.prefix'];
    }
    /**
     * Get the classes that should be allowed during unserialization.
     *
     * @return array|bool|null
     */
    protected function get_serializable_classes(array $config)
    {
        return $this->app['config']['cache.serializable_classes'] ?? null;
    }
    /**
     * Get the cache connection configuration.
     *
     * @param  string  $name
     * @return array|null
     */
    protected function get_config($name)
    {
        return $name !== 'null' ? $this->app['config']["cache.stores.{$name}"] : ['driver' => 'null'];
    }
    /**
     * Get the default cache driver name.
     *
     * @return string
     */
    public function get_default_driver()
    {
        return $this->app['config']['cache.default'] ?? 'null';
    }
    /**
     * Set the default cache driver name.
     *
     * @param  string  $name
     */
    public function set_default_driver($name): void
    {
        $this->app['config']['cache.default'] = $name;
    }
    /**
     * Unset the given driver instances.
     *
     * @param  array|string|null  $name
     * @return $this
     */
    public function forget_driver($name = null): static
    {
        $name ??= $this->get_default_driver();
        foreach ((array) $name as $cache_name) {
            if (isset($this->stores[$cache_name])) {
                unset($this->stores[$cache_name]);
            }
        }
        return $this;
    }
    /**
     * Disconnect the given driver and remove from local cache.
     *
     * @param  string|null  $name
     */
    public function purge($name = null): void
    {
        $name ??= $this->get_default_driver();
        unset($this->stores[$name]);
    }
    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     *
     * @param-closure-this  $this  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback): static
    {
        $this->custom_creators[$driver] = $callback->bind_to($this, $this);
        return $this;
    }
    /**
     * Set the application instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return $this
     */
    public function set_application($app): static
    {
        $this->app = $app;
        return $this;
    }
    /**
     * Dynamically call the default driver instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->store()->{$method}(...$parameters);
    }
}