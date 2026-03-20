<?php

declare (strict_types=1);
namespace Illuminate\Database\Connectors;

use PDO;
class Maria_Db_Connector extends My_Sql_Connector
{
    /**
     * Get the sql_mode value.
     */
    protected function get_sql_mode(PDO $connection, array $config): ?string
    {
        if (isset($config['modes'])) {
            return implode(',', $config['modes']);
        }
        if (!isset($config['strict'])) {
            return null;
        }
        if (!$config['strict']) {
            return 'NO_ENGINE_SUBSTITUTION';
        }
        return 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
    }
}