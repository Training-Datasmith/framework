<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Processors;

use Illuminate\Database\Query\Builder;
class Processor
{
    /**
     * Process the results of a "select" query.
     *
     * @param  array  $results
     * @return array
     */
    public function process_select(Builder $query, $results)
    {
        return $results;
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
        $query->get_connection()->insert($sql, $values);
        $id = $query->get_connection()->get_pdo()->last_insert_id($sequence);
        return is_numeric($id) ? (int) $id : $id;
    }
    /**
     * Process the results of a schemas query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, path: string|null, default: bool}>
     */
    public function process_schemas($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return [
                'name' => $result->name,
                'path' => $result->path ?? null,
                // SQLite Only...
                'default' => (bool) $result->default,
            ];
        }, $results);
    }
    /**
     * Process the results of a tables query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, schema: string|null, schema_qualified_name: string, size: int|null, comment: string|null, collation: string|null, engine: string|null}>
     */
    public function process_tables($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return [
                'name' => $result->name,
                'schema' => $result->schema ?? null,
                'schema_qualified_name' => isset($result->schema) ? $result->schema . '.' . $result->name : $result->name,
                'size' => isset($result->size) ? (int) $result->size : null,
                'comment' => $result->comment ?? null,
                // MySQL and PostgreSQL
                'collation' => $result->collation ?? null,
                // MySQL only
                'engine' => $result->engine ?? null,
            ];
        }, $results);
    }
    /**
     * Process the results of a views query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, schema: string, schema_qualified_name: string, definition: string}>
     */
    public function process_views($results): array
    {
        return array_map(function (array $result): array {
            $result = (object) $result;
            return ['name' => $result->name, 'schema' => $result->schema ?? null, 'schema_qualified_name' => isset($result->schema) ? $result->schema . '.' . $result->name : $result->name, 'definition' => $result->definition];
        }, $results);
    }
    /**
     * Process the results of a types query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, schema: string, type: string, type: string, category: string, implicit: bool}>
     */
    public function process_types($results)
    {
        return $results;
    }
    /**
     * Process the results of a columns query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, type: string, type_name: string, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null}>
     */
    public function process_columns($results)
    {
        return $results;
    }
    /**
     * Process the results of an indexes query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, columns: list<string>, type: string, unique: bool, primary: bool}>
     */
    public function process_indexes($results)
    {
        return $results;
    }
    /**
     * Process the results of a foreign keys query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, columns: list<string>, foreign_schema: string, foreign_table: string, foreign_columns: list<string>, on_update: string, on_delete: string}>
     */
    public function process_foreign_keys($results)
    {
        return $results;
    }
}