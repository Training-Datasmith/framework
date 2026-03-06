<?php

declare(strict_types=1);

namespace Illuminate\Queue;

use Closure;
use Illuminate\Contracts\Queue\Factory as FactoryContract;
use Illuminate\Contracts\Queue\Monitor as MonitorContract;
use InvalidArgumentException;

/**
 * @mixin \Illuminate\Contracts\Queue\Queue
 */
class QueueManager implements FactoryContract, MonitorContract
{
    /**
     * The array of resolved queue connections.
     *
     * @var array
     */
    protected $connections = [];

    /**
     * The array of resolved queue connectors.
     *
     * @var array
     */
    protected $connectors = [];

    /**
     * Create a new queue manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct(
        /**
         * The application instance.
         */
        protected $app
    ) {
    }

    /**
     * Register an event listener for the before job event.
     *
     * @param  mixed  $callback
     */
    public function before($callback): void
    {
        $this->app['events']->listen(Events\JobProcessing::class, $callback);
    }

    /**
     * Register an event listener for the after job event.
     *
     * @param  mixed  $callback
     */
    public function after($callback): void
    {
        $this->app['events']->listen(Events\JobProcessed::class, $callback);
    }

    /**
     * Register an event listener for the exception occurred job event.
     *
     * @param  mixed  $callback
     */
    public function exceptionOccurred($callback): void
    {
        $this->app['events']->listen(Events\JobExceptionOccurred::class, $callback);
    }

    /**
     * Register an event listener for the daemon queue loop.
     *
     * @param  mixed  $callback
     */
    public function looping($callback): void
    {
        $this->app['events']->listen(Events\Looping::class, $callback);
    }

    /**
     * Register an event listener for the failed job event.
     *
     * @param  mixed  $callback
     */
    public function failing($callback): void
    {
        $this->app['events']->listen(Events\JobFailed::class, $callback);
    }

    /**
     * Register an event listener for the daemon queue starting.
     *
     * @param  mixed  $callback
     */
    public function starting($callback): void
    {
        $this->app['events']->listen(Events\WorkerStarting::class, $callback);
    }

    /**
     * Register an event listener for the daemon queue stopping.
     *
     * @param  mixed  $callback
     */
    public function stopping($callback): void
    {
        $this->app['events']->listen(Events\WorkerStopping::class, $callback);
    }

    /**
     * Determine if the driver is connected.
     *
     * @param  string|null  $name
     */
    public function connected($name = null): bool
    {
        return isset($this->connections[$name ?: $this->getDefaultDriver()]);
    }

    /**
     * Resolve a queue connection instance.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connection($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        // If the connection has not been resolved yet we will resolve it now as all
        // of the connections are resolved when they are actually needed so we do
        // not make any unnecessary connection to the various queue end-points.
        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);

            $this->connections[$name]->setContainer($this->app);
        }

        return $this->connections[$name];
    }

    /**
     * Resolve a queue connection.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Queue\Queue
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("The [{$name}] queue connection has not been configured.");
        }

        $queue = $this->getConnector($config['driver'])
            ->connect($config)
            ->setConnectionName($name);

        if (method_exists($queue, 'setConfig')) {
            $queue->setConfig($config);
        }

        return $queue;
    }

    /**
     * Get the connector for a given driver.
     *
     * @param  string  $driver
     * @return \Illuminate\Queue\Connectors\ConnectorInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function getConnector($driver): mixed
    {
        if (! isset($this->connectors[$driver])) {
            throw new InvalidArgumentException("No connector for [$driver].");
        }

        return call_user_func($this->connectors[$driver]);
    }

    /**
     * Pause a queue by its connection and name.
     *
     * @param  string  $connection
     * @param  string  $queue
     */
    public function pause($connection, $queue): void
    {
        $this->app['cache']
            ->store()
            ->forever("illuminate:queue:paused:{$connection}:{$queue}", true);

        $this->app['events']->dispatch(
            new Events\QueuePaused($connection, $queue)
        );
    }

    /**
     * Pause a queue by its connection and name for a given amount of time.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  \DateTimeInterface|\DateInterval|int  $ttl
     */
    public function pauseFor($connection, $queue, $ttl): void
    {
        $this->app['cache']
            ->store()
            ->put("illuminate:queue:paused:{$connection}:{$queue}", true, $ttl);

        $this->app['events']->dispatch(
            new Events\QueuePaused($connection, $queue, $ttl)
        );
    }

    /**
     * Resume a paused queue by its connection and name.
     *
     * @param  string  $connection
     * @param  string  $queue
     */
    public function resume($connection, $queue): void
    {
        $this->app['cache']
            ->store()
            ->forget("illuminate:queue:paused:{$connection}:{$queue}");

        $this->app['events']->dispatch(
            new Events\QueueResumed($connection, $queue)
        );
    }

    /**
     * Determine if a queue is paused.
     *
     * @param  string  $connection
     * @param  string  $queue
     */
    public function isPaused($connection, $queue): bool
    {
        return (bool) $this->app['cache']
            ->store()
            ->get("illuminate:queue:paused:{$connection}:{$queue}", false);
    }

    /**
     * Indicate that queue workers should not poll for restart or pause signals.
     *
     * This prevents the workers from hitting the application cache to determine if they need to pause or restart.
     */
    public function withoutInterruptionPolling(): void
    {
        Worker::$restartable = false;
        Worker::$pausable = false;
    }

    /**
     * Add a queue connection resolver.
     *
     * @param  string  $driver
     */
    public function extend($driver, Closure $resolver): void
    {
        $this->addConnector($driver, $resolver);
    }

    /**
     * Add a queue connection resolver.
     *
     * @param  string  $driver
     */
    public function addConnector($driver, Closure $resolver): void
    {
        $this->connectors[$driver] = $resolver;
    }

    /**
     * Get the queue connection configuration.
     *
     * @param  string  $name
     * @return array|null
     */
    protected function getConfig($name)
    {
        if (! is_null($name) && $name !== 'null') {
            return $this->app['config']["queue.connections.{$name}"];
        }

        return ['driver' => 'null'];
    }

    /**
     * Get the name of the default queue connection.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['queue.default'];
    }

    /**
     * Set the name of the default queue connection.
     *
     * @param  string  $name
     */
    public function setDefaultDriver($name): void
    {
        $this->app['config']['queue.default'] = $name;
    }

    /**
     * Get the full name for the given connection.
     *
     * @param  string|null  $connection
     * @return string
     */
    public function getName($connection = null)
    {
        return $connection ?: $this->getDefaultDriver();
    }

    /**
     * Get the application instance used by the manager.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    public function getApplication()
    {
        return $this->app;
    }

    /**
     * Set the application instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return $this
     */
    public function setApplication($app): static
    {
        $this->app = $app;

        foreach ($this->connections as $connection) {
            $connection->setContainer($app);
        }

        return $this;
    }

    /**
     * Dynamically pass calls to the default connection.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}
