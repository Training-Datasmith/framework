<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Memcached;
class Memcached_Connector
{
    /**
     * Create a new Memcached connection.
     *
     * @param  string|null  $connectionId
     * @return \Memcached
     */
    public function connect(array $servers, $connection_id = null, array $options = [], array $credentials = [])
    {
        $memcached = $this->get_memcached($connection_id, $credentials, $options);
        if (!$memcached->get_server_list()) {
            // For each server in the array, we'll just extract the configuration and add
            // the server to the Memcached connection. Once we have added all of these
            // servers we'll verify the connection is successful and return it back.
            foreach ($servers as $server) {
                $memcached->add_server($server['host'], $server['port'], $server['weight']);
            }
        }
        return $memcached;
    }
    /**
     * Get a new Memcached instance.
     *
     * @param  string|null  $connectionId
     * @return \Memcached
     */
    protected function get_memcached($connection_id, array $credentials, array $options)
    {
        $memcached = $this->create_memcached_instance($connection_id);
        if (count($credentials) === 2) {
            $this->set_credentials($memcached, $credentials);
        }
        if (count($options)) {
            $memcached->set_options($options);
        }
        return $memcached;
    }
    /**
     * Create the Memcached instance.
     *
     * @param  string|null  $connectionId
     */
    protected function create_memcached_instance($connection_id): \Memcached
    {
        return empty($connection_id) ? new Memcached() : new Memcached($connection_id);
    }
    /**
     * Set the SASL credentials on the Memcached connection.
     *
     * @param  \Memcached  $memcached
     * @param  array  $credentials
     * @return void
     */
    protected function set_credentials($memcached, $credentials)
    {
        [$username, $password] = $credentials;
        $memcached->set_option(Memcached::OPT_BINARY_PROTOCOL, true);
        $memcached->set_sasl_auth_data($username, $password);
    }
}