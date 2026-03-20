<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

class Database_Busy
{
    /**
     * Create a new event instance.
     *
     * @param  string  $connectionName  The database connection name.
     * @param  int  $connections  The number of open connections.
     */
    public function __construct(public $connection_name, public $connections)
    {
    }
}