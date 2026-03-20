<?php

declare (strict_types=1);
namespace Illuminate\Database\Connectors;

interface Connector_Interface
{
    /**
     * Establish a database connection.
     *
     * @return \PDO
     */
    public function connect(array $config);
}