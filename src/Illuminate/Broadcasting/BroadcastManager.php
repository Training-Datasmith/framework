<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Ably\Ably_Rest;
use Closure;
use Guzzle_Http\Client as GuzzleClient;
use Illuminate\Broadcasting\Broadcasters\Ably_Broadcaster;
use Illuminate\Broadcasting\Broadcasters\Log_Broadcaster;
use Illuminate\Broadcasting\Broadcasters\Null_Broadcaster;
use Illuminate\Broadcasting\Broadcasters\Pusher_Broadcaster;
use Illuminate\Broadcasting\Broadcasters\Redis_Broadcaster;
use Illuminate\Bus\Unique_Lock;
use Illuminate\Contracts\Broadcasting\Factory as FactoryContract;
use Illuminate\Contracts\Broadcasting\Should_Be_Unique;
use Illuminate\Contracts\Broadcasting\Should_Broadcast_Now;
use Illuminate\Contracts\Broadcasting\Should_Rescue;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcherContract;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Caches_Routes;
use InvalidArgumentException;
use Psr\Log\Logger_Interface;
use Pusher\Pusher;
use RuntimeException;
use Throwable;
/**
 * @mixin \Illuminate\Contracts\Broadcasting\Broadcaster
 */
class Broadcast_Manager implements Factory_Contract
{
    /**
     * The array of resolved broadcast drivers.
     *
     * @var array
     */
    protected $drivers = [];
    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $custom_creators = [];
    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $app
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
     * Register the routes for handling broadcast channel authentication and sockets.
     */
    public function routes(?array $attributes = null): void
    {
        if ($this->app instanceof Caches_Routes && $this->app->routes_are_cached()) {
            return;
        }
        $attributes = $attributes ?: ['middleware' => ['web']];
        $this->app['router']->group($attributes, function ($router): void {
            $router->match(['get', 'post'], '/broadcasting/auth', '\\' . Broadcast_Controller::class . '@authenticate')->without_middleware([\Illuminate\Foundation\Http\Middleware\Verify_Csrf_Token::class]);
        });
    }
    /**
     * Register the routes for handling broadcast user authentication.
     */
    public function user_routes(?array $attributes = null): void
    {
        if ($this->app instanceof Caches_Routes && $this->app->routes_are_cached()) {
            return;
        }
        $attributes = $attributes ?: ['middleware' => ['web']];
        $this->app['router']->group($attributes, function ($router): void {
            $router->match(['get', 'post'], '/broadcasting/user-auth', '\\' . Broadcast_Controller::class . '@authenticateUser')->without_middleware([\Illuminate\Foundation\Http\Middleware\Verify_Csrf_Token::class]);
        });
    }
    /**
     * Register the routes for handling broadcast authentication and sockets.
     *
     * Alias of "routes" method.
     */
    public function channel_routes(?array $attributes = null): void
    {
        $this->routes($attributes);
    }
    /**
     * Get the socket ID for the given request.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return string|null
     */
    public function socket($request = null)
    {
        if (!$request && !$this->app->bound('request')) {
            return;
        }
        $request = $request ?: $this->app['request'];
        return $request->header('X-Socket-ID');
    }
    /**
     * Begin sending an anonymous broadcast to the given channels.
     */
    public function on(Channel|string|array $channels): Anonymous_Event
    {
        return new Anonymous_Event($channels);
    }
    /**
     * Begin sending an anonymous broadcast to the given private channels.
     */
    public function private(string $channel): Anonymous_Event
    {
        return $this->on(new Private_Channel($channel));
    }
    /**
     * Begin sending an anonymous broadcast to the given presence channels.
     */
    public function presence(string $channel): Anonymous_Event
    {
        return $this->on(new Presence_Channel($channel));
    }
    /**
     * Begin broadcasting an event.
     *
     * @param  mixed  $event
     */
    public function event($event = null): \Illuminate\Broadcasting\Pending_Broadcast
    {
        return new Pending_Broadcast($this->app->make('events'), $event);
    }
    /**
     * Queue the given event for broadcast.
     *
     * @param  mixed  $event
     * @return void
     */
    public function queue($event)
    {
        if ($event instanceof Should_Broadcast_Now || is_object($event) && method_exists($event, 'shouldBroadcastNow') && $event->should_broadcast_now()) {
            $dispatch = fn() => $this->app->make(Bus_Dispatcher_Contract::class)->dispatch_now(new Broadcast_Event(clone $event));
            return $event instanceof Should_Rescue ? $this->rescue($dispatch) : $dispatch();
        }
        $queue = match (true) {
            method_exists($event, 'broadcastQueue') => $event->broadcast_queue(),
            isset($event->broadcast_queue) => $event->broadcast_queue,
            isset($event->queue) => $event->queue,
            default => null,
        };
        $broadcast_event = new Broadcast_Event(clone $event);
        if ($event instanceof Should_Be_Unique) {
            $broadcast_event = new Unique_Broadcast_Event(clone $event);
            if ($this->must_be_unique_and_cannot_acquire_lock($broadcast_event)) {
                return;
            }
        }
        $push = fn() => $this->app->make('queue')->connection($event->connection ?? null)->push_on($queue, $broadcast_event);
        $event instanceof Should_Rescue ? $this->rescue($push) : $push();
    }
    /**
     * Determine if the broadcastable event must be unique and determine if we can acquire the necessary lock.
     *
     * @param  mixed  $event
     */
    protected function must_be_unique_and_cannot_acquire_lock($event): bool
    {
        return !(new Unique_Lock(method_exists($event, 'uniqueVia') ? $event->unique_via() : $this->app->make(Cache::class)))->acquire($event);
    }
    /**
     * Get a driver instance.
     *
     * @param  string|null  $driver
     * @return mixed
     */
    public function connection($driver = null)
    {
        return $this->driver($driver);
    }
    /**
     * Get a driver instance.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function driver($name = null)
    {
        $name = $name ?: $this->get_default_driver();
        return $this->drivers[$name] = $this->get($name);
    }
    /**
     * Attempt to get the connection from the local cache.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected function get($name)
    {
        return $this->drivers[$name] ?? $this->resolve($name);
    }
    /**
     * Resolve the given broadcaster.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->get_config($name);
        if (is_null($config)) {
            throw new InvalidArgumentException("Broadcast connection [{$name}] is not defined.");
        }
        if (isset($this->custom_creators[$config['driver']])) {
            return $this->call_custom_creator($config);
        }
        $driver_method = 'create' . ucfirst((string) $config['driver']) . 'Driver';
        if (!method_exists($this, $driver_method)) {
            throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
        }
        try {
            return $this->{$driver_method}($config);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to create broadcaster for connection \"{$name}\" with error: {$e->get_message()}.", 0, $e);
        }
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
     * Create an instance of the driver.
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected function create_reverb_driver(array $config): \Illuminate\Broadcasting\Broadcasters\Pusher_Broadcaster
    {
        return $this->create_pusher_driver($config);
    }
    /**
     * Create an instance of the driver.
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected function create_pusher_driver(array $config): \Illuminate\Broadcasting\Broadcasters\Pusher_Broadcaster
    {
        return new Pusher_Broadcaster($this->pusher($config), $config['jsonp'] ?? false);
    }
    /**
     * Get a Pusher instance for the given configuration.
     *
     * @return \Pusher\Pusher
     */
    public function pusher(array $config): \Pusher\Pusher
    {
        $guzzle_client = new Guzzle_Client(array_merge(['connect_timeout' => 10, 'crypto_method' => Stream_crypto_method_tl_Sv1_2_client, 'timeout' => 30], $config['client_options'] ?? []));
        $pusher = new Pusher($config['key'], $config['secret'], $config['app_id'], $config['options'] ?? [], $guzzle_client);
        if ($config['log'] ?? false) {
            $pusher->set_logger($this->app->make(Logger_Interface::class));
        }
        return $pusher;
    }
    /**
     * Create an instance of the driver.
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected function create_ably_driver(array $config): \Illuminate\Broadcasting\Broadcasters\Ably_Broadcaster
    {
        return new Ably_Broadcaster($this->ably($config));
    }
    /**
     * Get an Ably instance for the given configuration.
     *
     * @return \Ably\AblyRest
     */
    public function ably(array $config)
    {
        return new Ably_Rest($config);
    }
    /**
     * Create an instance of the driver.
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected function create_redis_driver(array $config): \Illuminate\Broadcasting\Broadcasters\Redis_Broadcaster
    {
        return new Redis_Broadcaster($this->app->make('redis'), $config['connection'] ?? null, $this->app['config']->get('database.redis.options.prefix', ''));
    }
    /**
     * Create an instance of the driver.
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected function create_log_driver(array $config): \Illuminate\Broadcasting\Broadcasters\Log_Broadcaster
    {
        return new Log_Broadcaster($this->app->make(Logger_Interface::class));
    }
    /**
     * Create an instance of the driver.
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected function create_null_driver(array $config): \Illuminate\Broadcasting\Broadcasters\Null_Broadcaster
    {
        return new Null_Broadcaster();
    }
    /**
     * Get the connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function get_config($name)
    {
        if (!is_null($name) && $name !== 'null') {
            return $this->app['config']["broadcasting.connections.{$name}"];
        }
        return ['driver' => 'null'];
    }
    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function get_default_driver()
    {
        return $this->app['config']['broadcasting.default'];
    }
    /**
     * Set the default driver name.
     *
     * @param  string  $name
     */
    public function set_default_driver($name): void
    {
        $this->app['config']['broadcasting.default'] = $name;
    }
    /**
     * Disconnect the given driver / connection and remove it from local cache.
     *
     * @param  string|null  $name
     */
    public function purge($name = null): void
    {
        $name ??= $this->get_default_driver();
        unset($this->drivers[$name]);
    }
    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     * @return $this
     */
    public function extend($driver, Closure $callback): static
    {
        $this->custom_creators[$driver] = $callback;
        return $this;
    }
    /**
     * Execute the given callback using "rescue" if possible.
     *
     * @return mixed
     */
    protected function rescue(Closure $callback)
    {
        if (function_exists('rescue')) {
            return rescue($callback);
        }
        return $callback();
    }
    /**
     * Get the application instance used by the manager.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    public function get_application()
    {
        return $this->app;
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
     * Forget all of the resolved driver instances.
     *
     * @return $this
     */
    public function forget_drivers(): static
    {
        $this->drivers = [];
        return $this;
    }
    /**
     * Dynamically call the default driver instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->{$method}(...$parameters);
    }
}