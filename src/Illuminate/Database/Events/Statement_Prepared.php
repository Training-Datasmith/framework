<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

class Statement_Prepared
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Connection  $connection  The database connection instance.
     * @param  \PDOStatement  $statement  The PDO statement.
     */
    public function __construct(public $connection, public $statement)
    {
    }
}