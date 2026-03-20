<?php

declare (strict_types=1);
namespace Illuminate\Database\Connectors;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Maria_Db_Connection;
use Illuminate\Database\My_Sql_Connection;
use Illuminate\Database\Postgres_Connection;
use Illuminate\Database\Sq_Lite_Connection;
use Illuminate\Database\Sql_Server_Connection;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use PDOException;
class Connection_Factory
{
    /**
     * Create a new connection factory instance.
     */
    public function __construct(
        /**
         * The IoC container instance.
         */
        protected \Illuminate\Contracts\Container\Container $container
    )
    {
    }
    /**
     * Establish a PDO connection based on the configuration.
     *
     * @param  string|null  $name
     * @return \Illuminate\Database\Connection
     */
    public function make(array $config, $name = null)
    {
        $config = $this->parse_config($config, $name);
        if (isset($config['read'])) {
            return $this->create_read_write_connection($config);
        }
        return $this->create_single_connection($config);
    }
    /**
     * Parse and prepare the database configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function parse_config(array $config, $name)
    {
        return Arr::add(Arr::add($config, 'prefix', ''), 'name', $name);
    }
    /**
     * Create a single database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function create_single_connection(array $config)
    {
        $pdo = $this->create_pdo_resolver($config);
        return $this->create_connection($config['driver'], $pdo, $config['database'], $config['prefix'], $config);
    }
    /**
     * Create a read / write database connection instance.
     */
    protected function create_read_write_connection(array $config): \Illuminate\Database\Connection
    {
        $connection = $this->create_single_connection($this->get_write_config($config));
        return $connection->set_read_pdo($this->create_read_pdo($config))->set_read_pdo_config($this->get_read_config($config));
    }
    /**
     * Create a new PDO instance for reading.
     *
     * @return \Closure
     */
    protected function create_read_pdo(array $config)
    {
        return $this->create_pdo_resolver($this->get_read_config($config));
    }
    /**
     * Get the read configuration for a read / write connection.
     *
     * @return array
     */
    protected function get_read_config(array $config)
    {
        return $this->merge_read_write_config($config, $this->get_read_write_config($config, 'read'));
    }
    /**
     * Get the write configuration for a read / write connection.
     *
     * @return array
     */
    protected function get_write_config(array $config)
    {
        return $this->merge_read_write_config($config, $this->get_read_write_config($config, 'write'));
    }
    /**
     * Get a read / write level configuration.
     *
     * @param  string  $type
     * @return array
     */
    protected function get_read_write_config(array $config, $type)
    {
        return isset($config[$type][0]) ? Arr::random($config[$type]) : $config[$type];
    }
    /**
     * Merge a configuration for a read / write connection.
     */
    protected function merge_read_write_config(array $config, array $merge): array
    {
        return Arr::except(array_merge($config, $merge), ['read', 'write']);
    }
    /**
     * Create a new Closure that resolves to a PDO instance.
     *
     * @return \Closure
     */
    protected function create_pdo_resolver(array $config)
    {
        return array_key_exists('host', $config) ? $this->create_pdo_resolver_with_hosts($config) : $this->create_pdo_resolver_without_hosts($config);
    }
    /**
     * Create a new Closure that resolves to a PDO instance with a specific host or an array of hosts.
     *
     * @return \Closure
     * @throws \PDOException
     */
    protected function create_pdo_resolver_with_hosts(array $config)
    {
        return function () use ($config) {
            foreach (Arr::shuffle($this->parse_hosts($config)) as $host) {
                $config['host'] = $host;
                try {
                    return $this->create_connector($config)->connect($config);
                } catch (PDOException) {
                    continue;
                }
            }
            if (isset($e)) {
                throw $e;
            }
        };
    }
    /**
     * Parse the hosts configuration item into an array.
     *
     *
     * @throws \InvalidArgumentException
     */
    protected function parse_hosts(array $config): array
    {
        $hosts = Arr::wrap($config['host']);
        if (empty($hosts)) {
            throw new InvalidArgumentException('Database hosts array is empty.');
        }
        return $hosts;
    }
    /**
     * Create a new Closure that resolves to a PDO instance where there is no configured host.
     *
     * @return \Closure
     */
    protected function create_pdo_resolver_without_hosts(array $config)
    {
        return fn() => $this->create_connector($config)->connect($config);
    }
    /**
     * Create a connector instance based on the configuration.
     *
     * @return \Illuminate\Database\Connectors\ConnectorInterface
     * @throws \InvalidArgumentException
     */
    public function create_connector(array $config)
    {
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('A driver must be specified.');
        }
        if ($this->container->bound($key = "db.connector.{$config['driver']}")) {
            return $this->container->make($key);
        }
        return match ($config['driver']) {
            'mysql' => new My_Sql_Connector(),
            'mariadb' => new Maria_Db_Connector(),
            'pgsql' => new Postgres_Connector(),
            'sqlite' => new Sq_Lite_Connector(),
            'sqlsrv' => new Sql_Server_Connector(),
            default => throw new InvalidArgumentException("Unsupported driver [{$config['driver']}]."),
        };
    }
    /**
     * Create a new connection instance.
     *
     * @param  string  $driver
     * @param  \PDO|\Closure  $connection
     * @param  string  $database
     * @param  string  $prefix
     * @return \Illuminate\Database\Connection
     * @throws \InvalidArgumentException
     */
    protected function create_connection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        if ($resolver = Connection::get_resolver($driver)) {
            return $resolver($connection, $database, $prefix, $config);
        }
        return match ($driver) {
            'mysql' => new My_Sql_Connection($connection, $database, $prefix, $config),
            'mariadb' => new Maria_Db_Connection($connection, $database, $prefix, $config),
            'pgsql' => new Postgres_Connection($connection, $database, $prefix, $config),
            'sqlite' => new Sq_Lite_Connection($connection, $database, $prefix, $config),
            'sqlsrv' => new Sql_Server_Connection($connection, $database, $prefix, $config),
            default => throw new InvalidArgumentException("Unsupported driver [{$driver}]."),
        };
    }
}