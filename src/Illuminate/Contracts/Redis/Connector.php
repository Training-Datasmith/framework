<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Redis;

interface Connector
{
    /**
     * Create a connection to a Redis cluster.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function connect(array $config, array $options);
    /**
     * Create a connection to a Redis instance.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function connect_to_cluster(array $config, array $cluster_options, array $options);
}