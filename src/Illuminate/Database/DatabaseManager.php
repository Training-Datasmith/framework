<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Database\Events\Connection_Established;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Configuration_Url_Parser;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
/**
 * @mixin \Illuminate\Database\Connection
 */
class Database_Manager implements Connection_Resolver_Interface
{
    use Macroable {
        __call as macroCall;
    }
    /**
     * The active connection instances.
     *
     * @var array<string, \Illuminate\Database\Connection>
     */
    protected $connections = [];
    /**
     * The dynamically configured (DB::build) connection configurations.
     *
     * @var array<string, array>
     */
    protected $dynamic_connection_configurations = [];
    /**
     * The custom connection resolvers.
     *
     * @var array<string, callable>
     */
    protected $extensions = [];
    /**
     * The callback to be executed to reconnect to a database.
     *
     * @var callable
     */
    protected $reconnector;
    /**
     * Create a new database manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct(
        /**
         * The application instance.
         */
        protected $app,
        /**
         * The database connection factory instance.
         */
        protected \Illuminate\Database\Connectors\Connection_Factory $factory
    )
    {
        $this->reconnector = function ($connection): void {
            $connection->set_pdo($this->reconnect($connection->get_name_with_read_write_type())->get_raw_pdo());
        };
    }
    /**
     * Get a database connection instance.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Illuminate\Database\Connection
     */
    public function connection($name = null)
    {
        [$database, $type] = $this->parse_connection_name($name = enum_value($name) ?: $this->get_default_connection());
        // If we haven't created this connection, we'll create it based on the config
        // provided in the application. Once we've created the connections we will
        // set the "fetch mode" for PDO which determines the query return types.
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->configure($this->make_connection($database), $type);
            $this->dispatch_connection_established_event($this->connections[$name]);
        }
        return $this->connections[$name];
    }
    /**
     * Build a database connection instance from the given configuration.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function build(array $config)
    {
        $config['name'] ??= static::calculate_dynamic_connection_name($config);
        $this->dynamic_connection_configurations[$config['name']] = $config;
        return $this->connect_using($config['name'], $config, true);
    }
    /**
     * Calculate the dynamic connection name for an on-demand connection based on its configuration.
     */
    public static function calculate_dynamic_connection_name(array $config): string
    {
        return 'dynamic_' . md5((new Collection($config))->map(fn($value, $key): string => $key . (is_string($value) || is_int($value) ? $value : ''))->implode(''));
    }
    /**
     * Get a database connection instance from the given configuration.
     *
     * @param  \UnitEnum|string  $name
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function connect_using(string $name, array $config, bool $force = false)
    {
        if ($force) {
            $this->purge($name = enum_value($name));
        }
        if (isset($this->connections[$name])) {
            throw new RuntimeException("Cannot establish connection [{$name}] because another connection with that name already exists.");
        }
        $connection = $this->configure($this->factory->make($config, $name), null);
        $this->dispatch_connection_established_event($connection);
        return tap($connection, fn($connection): \Illuminate\Database\Connection => $this->connections[$name] = $connection);
    }
    /**
     * Parse the connection into an array of the name and read / write type.
     *
     * @param  string  $name
     * @return array
     */
    protected function parse_connection_name($name)
    {
        return Str::ends_with($name, ['::read', '::write']) ? explode('::', $name, 2) : [$name, null];
    }
    /**
     * Make the database connection instance.
     *
     * @param  string  $name
     * @return \Illuminate\Database\Connection
     */
    protected function make_connection($name)
    {
        $config = $this->configuration($name);
        // First we will check by the connection name to see if an extension has been
        // registered specifically for that connection. If it has we will call the
        // Closure and pass it the config allowing it to resolve the connection.
        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }
        // Next we will check to see if an extension has been registered for a driver
        // and will call the Closure if so, which allows us to have a more generic
        // resolver for the drivers themselves which applies to all connections.
        if (isset($this->extensions[$driver = $config['driver']])) {
            return call_user_func($this->extensions[$driver], $config, $name);
        }
        return $this->factory->make($config, $name);
    }
    /**
     * Get the configuration for a connection.
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function configuration(string $name)
    {
        $connections = $this->app['config']['database.connections'];
        $config = $this->dynamic_connection_configurations[$name] ?? Arr::get($connections, $name);
        if (is_null($config)) {
            throw new InvalidArgumentException("Database connection [{$name}] not configured.");
        }
        return (new Configuration_Url_Parser())->parse_configuration($config);
    }
    /**
     * Prepare the database connection instance.
     *
     * @param  string  $type
     * @return \Illuminate\Database\Connection
     */
    protected function configure(Connection $connection, $type)
    {
        $connection = $this->set_pdo_for_type($connection, $type)->set_read_write_type($type);
        // First we'll set the fetch mode and a few other dependencies of the database
        // connection. This method basically just configures and prepares it to get
        // used by the application. Once we're finished we'll return it back out.
        if ($this->app->bound('events')) {
            $connection->set_event_dispatcher($this->app['events']);
        }
        if ($this->app->bound('db.transactions')) {
            $connection->set_transaction_manager($this->app['db.transactions']);
        }
        // Here we'll set a reconnector callback. This reconnector can be any callable
        // so we will set a Closure to reconnect from this manager with the name of
        // the connection, which will allow us to reconnect from the connections.
        $connection->set_reconnector($this->reconnector);
        return $connection;
    }
    /**
     * Dispatch the ConnectionEstablished event if the event dispatcher is available.
     *
     * @return void
     */
    protected function dispatch_connection_established_event(Connection $connection)
    {
        if (!$this->app->bound('events')) {
            return;
        }
        $this->app['events']->dispatch(new Connection_Established($connection));
    }
    /**
     * Prepare the read / write mode for database connection instance.
     *
     * @param  string|null  $type
     */
    protected function set_pdo_for_type(Connection $connection, $type = null): Connection
    {
        if ($type === 'read') {
            $connection->set_pdo($connection->get_read_pdo());
        } elseif ($type === 'write') {
            $connection->set_read_pdo($connection->get_pdo());
        }
        return $connection;
    }
    /**
     * Disconnect from the given database and remove from local cache.
     *
     * @param  \UnitEnum|string|null  $name
     */
    public function purge($name = null): void
    {
        $this->disconnect($name = enum_value($name) ?: $this->get_default_connection());
        unset($this->connections[$name]);
    }
    /**
     * Disconnect from the given database.
     *
     * @param  \UnitEnum|string|null  $name
     */
    public function disconnect($name = null): void
    {
        if (isset($this->connections[$name = enum_value($name) ?: $this->get_default_connection()])) {
            $this->connections[$name]->disconnect();
        }
    }
    /**
     * Reconnect to the given database.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Illuminate\Database\Connection
     */
    public function reconnect($name = null)
    {
        $this->disconnect($name = enum_value($name) ?: $this->get_default_connection());
        if (!isset($this->connections[$name])) {
            return $this->connection($name);
        }
        return tap($this->refresh_pdo_connections($name), function (\Illuminate\Database\Connection $connection): void {
            $this->dispatch_connection_established_event($connection);
        });
    }
    /**
     * Set the default database connection for the callback execution.
     *
     * @param  \UnitEnum|string  $name
     * @return mixed
     */
    public function using_connection($name, callable $callback)
    {
        $previous_name = $this->get_default_connection();
        $this->set_default_connection($name = enum_value($name));
        try {
            return $callback();
        } finally {
            $this->set_default_connection($previous_name);
        }
    }
    /**
     * Refresh the PDO connections on a given connection.
     */
    protected function refresh_pdo_connections(string $name): \Illuminate\Database\Connection
    {
        [$database, $type] = $this->parse_connection_name($name);
        $fresh = $this->configure($this->make_connection($database), $type);
        return $this->connections[$name]->set_pdo($fresh->get_raw_pdo())->set_read_pdo($fresh->get_raw_read_pdo());
    }
    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function get_default_connection()
    {
        return $this->app['config']['database.default'];
    }
    /**
     * Set the default connection name.
     *
     * @param  string  $name
     */
    public function set_default_connection($name): void
    {
        $this->app['config']['database.default'] = $name;
    }
    /**
     * Get all of the supported drivers.
     *
     * @return string[]
     */
    public function supported_drivers(): array
    {
        return ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'];
    }
    /**
     * Get all of the drivers that are actually available.
     *
     * @return string[]
     */
    public function available_drivers(): array
    {
        return array_intersect($this->supported_drivers(), str_replace('dblib', 'sqlsrv', PDO::get_available_drivers()));
    }
    /**
     * Register an extension connection resolver.
     */
    public function extend(string $name, callable $resolver): void
    {
        $this->extensions[$name] = $resolver;
    }
    /**
     * Remove an extension connection resolver.
     */
    public function forget_extension(string $name): void
    {
        unset($this->extensions[$name]);
    }
    /**
     * Return all of the created connections.
     *
     * @return array<string, \Illuminate\Database\Connection>
     */
    public function get_connections()
    {
        return $this->connections;
    }
    /**
     * Set the database reconnector callback.
     */
    public function set_reconnector(callable $reconnector): void
    {
        $this->reconnector = $reconnector;
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
     * Dynamically pass methods to the default connection.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        return $this->connection()->{$method}(...$parameters);
    }
}