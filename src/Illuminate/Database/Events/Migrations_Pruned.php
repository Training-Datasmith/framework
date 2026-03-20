<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

use Illuminate\Database\Connection;
class Migrations_Pruned
{
    /**
     * The database connection instance.
     *
     * @var \Illuminate\Database\Connection
     */
    public $connection;
    /**
     * The database connection name.
     *
     * @var string|null
     */
    public $connection_name;
    /**
     * The path to the directory where migrations were pruned.
     *
     * @var string
     */
    public $path;
    /**
     * Create a new event instance.
     */
    public function __construct(Connection $connection, string $path)
    {
        $this->connection = $connection;
        $this->connection_name = $connection->get_name();
        $this->path = $path;
    }
}