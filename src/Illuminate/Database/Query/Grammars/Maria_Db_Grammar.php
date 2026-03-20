<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Join_Lateral_Clause;
use RuntimeException;
class Maria_Db_Grammar extends My_Sql_Grammar
{
    /**
     * Compile a "lateral join" clause.
     *
     *
     * @throws \RuntimeException
     */
    public function compile_join_lateral(Join_Lateral_Clause $join, string $expression): string
    {
        throw new RuntimeException('This database engine does not support lateral joins.');
    }
    /**
     * Compile a "JSON value cast" statement into SQL.
     *
     * @param  string  $value
     */
    public function compile_json_value_cast($value): string
    {
        return "json_query({$value}, '\$')";
    }
    /**
     * Compile a query to get the number of open connections for a database.
     */
    public function compile_thread_count(): string
    {
        return 'select variable_value as `Value` from information_schema.global_status where variable_name = \'THREADS_CONNECTED\'';
    }
    /**
     * Determine whether to use a legacy group limit clause for MySQL < 8.0.
     */
    public function use_legacy_group_limit(Builder $query): bool
    {
        return false;
    }
    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrap_json_selector($value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($value);
        return 'json_value(' . $field . $path . ')';
    }
}