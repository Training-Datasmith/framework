<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Processors;

use Illuminate\Database\Query\Builder;
class My_Sql_Processor extends Processor
{
    /**
     * Process the results of a column listing query.
     *
     * @deprecated Will be removed in a future Laravel version.
     *
     * @param  array  $results
     */
    public function process_column_listing($results): array
    {
        return array_map(fn($result) => ((object) $result)->column_name, $results);
    }
    /**
     * Process an  "insert get ID" query.
     *
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function process_insert_get_id(Builder $query, $sql, $values, $sequence = null)
    {
        $query->get_connection()->insert($sql, $values, $sequence);
        $id = $query->get_connection()->get_last_insert_id();
        return is_numeric($id) ? (int) $id : $id;
    }
    /** @inheritDoc */
    public function process_columns($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return ['name' => $result->name, 'type_name' => $result->type_name, 'type' => $result->type, 'collation' => $result->collation, 'nullable' => $result->nullable === 'YES', 'default' => $result->default, 'auto_increment' => $result->extra === 'auto_increment', 'comment' => $result->comment ?: null, 'generation' => $result->expression ? ['type' => match ($result->extra) {
                'STORED GENERATED' => 'stored',
                'VIRTUAL GENERATED' => 'virtual',
                default => null,
            }, 'expression' => $result->expression] : null];
        }, $results);
    }
    /** @inheritDoc */
    public function process_indexes($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return ['name' => $name = strtolower((string) $result->name), 'columns' => $result->columns ? explode(',', (string) $result->columns) : [], 'type' => strtolower((string) $result->type), 'unique' => (bool) $result->unique, 'primary' => $name === 'primary'];
        }, $results);
    }
    /** @inheritDoc */
    public function process_foreign_keys($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return ['name' => $result->name, 'columns' => explode(',', (string) $result->columns), 'foreign_schema' => $result->foreign_schema, 'foreign_table' => $result->foreign_table, 'foreign_columns' => explode(',', (string) $result->foreign_columns), 'on_update' => strtolower((string) $result->on_update), 'on_delete' => strtolower((string) $result->on_delete)];
        }, $results);
    }
}