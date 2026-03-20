<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Connection_Interface;
use Illuminate\Support\Arr;
abstract class Database_Inspection_Command extends Command
{
    /**
     * Get a human-readable name for the given connection.
     *
     * @param  string  $database
     * @return string
     * @deprecated
     */
    protected function get_connection_name(Connection_Interface $connection, $database)
    {
        return $connection->get_driver_title();
    }
    /**
     * Get the number of open connections for a database.
     *
     * @return int|null
     * @deprecated
     */
    protected function get_connection_count(Connection_Interface $connection)
    {
        return $connection->thread_count();
    }
    /**
     * Get the connection configuration details for the given connection.
     *
     * @param  string|null  $database
     * @return array
     */
    protected function get_config_from_database($database)
    {
        $database ??= config('database.default');
        return Arr::except(config('database.connections.' . $database), ['password']);
    }
}