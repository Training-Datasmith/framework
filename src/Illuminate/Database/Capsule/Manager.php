<?php

declare (strict_types=1);
namespace Illuminate\Database\Capsule;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connectors\Connection_Factory;
use Illuminate\Database\Database_Manager;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Traits\Capsule_Manager_Trait;
use PDO;
class Manager
{
    use Capsule_Manager_Trait;
    /**
     * The database manager instance.
     *
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $manager;
    /**
     * Create a new database capsule manager.
     */
    public function __construct(?Container $container = null)
    {
        $this->setup_container($container ?: new Container());
        // Once we have the container setup, we will setup the default configuration
        // options in the container "config" binding. This will make the database
        // manager work correctly out of the box without extreme configuration.
        $this->setup_default_configuration();
        $this->setup_manager();
    }
    /**
     * Setup the default database configuration options.
     *
     * @return void
     */
    protected function setup_default_configuration()
    {
        $this->container['config']['database.fetch'] = PDO::FETCH_OBJ;
        $this->container['config']['database.default'] = 'default';
    }
    /**
     * Build the database manager instance.
     *
     * @return void
     */
    protected function setup_manager()
    {
        $factory = new Connection_Factory($this->container);
        $this->manager = new Database_Manager($this->container, $factory);
    }
    /**
     * Get a connection instance from the global manager.
     *
     * @param  string|null  $connection
     * @return \Illuminate\Database\Connection
     */
    public static function connection($connection = null)
    {
        return static::$instance->get_connection($connection);
    }
    /**
     * Get a fluent query builder instance.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|string  $table
     * @param  string|null  $as
     * @param  string|null  $connection
     * @return \Illuminate\Database\Query\Builder
     */
    public static function table($table, $as = null, $connection = null)
    {
        return static::$instance->connection($connection)->table($table, $as);
    }
    /**
     * Get a schema builder instance.
     *
     * @param  string|null  $connection
     * @return \Illuminate\Database\Schema\Builder
     */
    public static function schema($connection = null)
    {
        return static::$instance->connection($connection)->get_schema_builder();
    }
    /**
     * Get a registered connection instance.
     *
     * @param  string|null  $name
     * @return \Illuminate\Database\Connection
     */
    public function get_connection($name = null)
    {
        return $this->manager->connection($name);
    }
    /**
     * Register a connection with the manager.
     *
     * @param  string  $name
     */
    public function add_connection(array $config, $name = 'default'): void
    {
        $connections = $this->container['config']['database.connections'];
        $connections[$name] = $config;
        $this->container['config']['database.connections'] = $connections;
    }
    /**
     * Bootstrap Eloquent so it is ready for usage.
     */
    public function boot_eloquent(): void
    {
        Eloquent::set_connection_resolver($this->manager);
        // If we have an event dispatcher instance, we will go ahead and register it
        // with the Eloquent ORM, allowing for model callbacks while creating and
        // updating "model" instances; however, it is not necessary to operate.
        if ($dispatcher = $this->get_event_dispatcher()) {
            Eloquent::set_event_dispatcher($dispatcher);
        }
    }
    /**
     * Set the fetch mode for the database connections.
     *
     * @param  int  $fetchMode
     * @return $this
     */
    public function set_fetch_mode($fetch_mode): static
    {
        $this->container['config']['database.fetch'] = $fetch_mode;
        return $this;
    }
    /**
     * Get the database manager instance.
     *
     * @return \Illuminate\Database\DatabaseManager
     */
    public function get_database_manager()
    {
        return $this->manager;
    }
    /**
     * Get the current event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher|null
     */
    public function get_event_dispatcher()
    {
        if ($this->container->bound('events')) {
            return $this->container['events'];
        }
    }
    /**
     * Set the event dispatcher instance to be used by connections.
     */
    public function set_event_dispatcher(Dispatcher $dispatcher): void
    {
        $this->container->instance('events', $dispatcher);
    }
    /**
     * Dynamically pass methods to the default connection.
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return static::connection()->{$method}(...$parameters);
    }
}