<?php

declare (strict_types=1);
namespace Illuminate\Database;

class Connection_Resolver implements Connection_Resolver_Interface
{
    /**
     * All of the registered connections.
     *
     * @var \Illuminate\Database\ConnectionInterface[]
     */
    protected $connections = [];
    /**
     * The default connection name.
     *
     * @var string
     */
    protected $default;
    /**
     * Create a new connection resolver instance.
     *
     * @param  array<string, \Illuminate\Database\ConnectionInterface>  $connections
     */
    public function __construct(array $connections = [])
    {
        foreach ($connections as $name => $connection) {
            $this->add_connection($name, $connection);
        }
    }
    /**
     * Get a database connection instance.
     *
     * @param  string|null  $name
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function connection($name = null)
    {
        if (is_null($name)) {
            $name = $this->get_default_connection();
        }
        return $this->connections[$name];
    }
    /**
     * Add a connection to the resolver.
     *
     * @param  string  $name
     */
    public function add_connection($name, Connection_Interface $connection): void
    {
        $this->connections[$name] = $connection;
    }
    /**
     * Check if a connection has been registered.
     *
     * @param  string  $name
     */
    public function has_connection($name): bool
    {
        return isset($this->connections[$name]);
    }
    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function get_default_connection()
    {
        return $this->default;
    }
    /**
     * Set the default connection name.
     *
     * @param  string  $name
     */
    public function set_default_connection($name): void
    {
        $this->default = $name;
    }
}