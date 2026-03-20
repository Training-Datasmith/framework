<?php

declare (strict_types=1);
namespace Illuminate\Database;

interface Connection_Resolver_Interface
{
    /**
     * Get a database connection instance.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function connection($name = null);
    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function get_default_connection();
    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function set_default_connection($name);
}