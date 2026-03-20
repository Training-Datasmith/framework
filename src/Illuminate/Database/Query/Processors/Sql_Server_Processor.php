<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Processors;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
class Sql_Server_Processor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function process_insert_get_id(Builder $query, $sql, $values, $sequence = null)
    {
        $connection = $query->get_connection();
        $connection->insert($sql, $values);
        if ($connection->get_config('odbc') === true) {
            $id = $this->process_insert_get_id_for_odbc($connection);
        } else {
            $id = $connection->get_pdo()->last_insert_id();
        }
        return is_numeric($id) ? (int) $id : $id;
    }
    /**
     * Process an "insert get ID" query for ODBC.
     *
     * @return int
     * @throws \Exception
     */
    protected function process_insert_get_id_for_odbc(Connection $connection)
    {
        $result = $connection->select_from_write_connection('SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS int) AS insertid');
        if (!$result) {
            throw new Exception('Unable to retrieve lastInsertID for ODBC.');
        }
        $row = $result[0];
        return is_object($row) ? $row->insertid : $row['insertid'];
    }
    /** @inheritDoc */
    public function process_columns($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            $type = match ($type_name = $result->type_name) {
                'binary', 'varbinary', 'char', 'varchar', 'nchar', 'nvarchar' => $result->length == -1 ? $type_name . '(max)' : $type_name . "({$result->length})",
                'decimal', 'numeric' => $type_name . "({$result->precision},{$result->places})",
                'float', 'datetime2', 'datetimeoffset', 'time' => $type_name . "({$result->precision})",
                default => $type_name,
            };
            return ['name' => $result->name, 'type_name' => $result->type_name, 'type' => $type, 'collation' => $result->collation, 'nullable' => (bool) $result->nullable, 'default' => $result->default, 'auto_increment' => (bool) $result->autoincrement, 'comment' => $result->comment, 'generation' => $result->expression ? ['type' => $result->persisted ? 'stored' : 'virtual', 'expression' => $result->expression] : null];
        }, $results);
    }
    /** @inheritDoc */
    public function process_indexes($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return ['name' => strtolower((string) $result->name), 'columns' => $result->columns ? explode(',', (string) $result->columns) : [], 'type' => strtolower((string) $result->type), 'unique' => (bool) $result->unique, 'primary' => (bool) $result->primary];
        }, $results);
    }
    /** @inheritDoc */
    public function process_foreign_keys($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return ['name' => $result->name, 'columns' => explode(',', (string) $result->columns), 'foreign_schema' => $result->foreign_schema, 'foreign_table' => $result->foreign_table, 'foreign_columns' => explode(',', (string) $result->foreign_columns), 'on_update' => strtolower(str_replace('_', ' ', $result->on_update)), 'on_delete' => strtolower(str_replace('_', ' ', $result->on_delete))];
        }, $results);
    }
}