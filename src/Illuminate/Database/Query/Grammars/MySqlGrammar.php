<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Join_Lateral_Clause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
class My_Sql_Grammar extends Grammar
{
    /**
     * The grammar specific operators.
     *
     * @var string[]
     */
    protected $operators = ['sounds like'];
    /**
     * Compile a select query into SQL.
     *
     * @return string
     */
    public function compile_select(Builder $query)
    {
        $sql = parent::compile_select($query);
        if ($query->timeout === null) {
            return $sql;
        }
        $milliseconds = $query->timeout * 1000;
        return preg_replace('/^select\b/i', 'select /*+ MAX_EXECUTION_TIME(' . $milliseconds . ') */', $sql, 1);
    }
    /**
     * Compile a "where like" clause.
     *
     * @param  array  $where
     */
    protected function where_like(Builder $query, $where): string
    {
        $where['operator'] = $where['not'] ? 'not ' : '';
        $where['operator'] .= $where['caseSensitive'] ? 'like binary' : 'like';
        return $this->where_basic($query, $where);
    }
    /**
     * Compile a "where null safe equals" clause.
     *
     * @param  array  $where
     */
    protected function where_null_safe_equals(Builder $query, $where): string
    {
        return $this->wrap($where['column']) . ' <=> ' . $this->parameter($where['value']);
    }
    /**
     * Add a "where null" clause to the query.
     */
    protected function where_null(Builder $query, array $where): string
    {
        $column_value = (string) $this->get_value($where['column']);
        if ($this->is_json_selector($column_value)) {
            [$field, $path] = $this->wrap_json_field_and_path($column_value);
            return '(json_extract(' . $field . $path . ') is null OR json_type(json_extract(' . $field . $path . ')) = \'NULL\')';
        }
        return parent::where_null($query, $where);
    }
    /**
     * Add a "where not null" clause to the query.
     */
    protected function where_not_null(Builder $query, array $where): string
    {
        $column_value = (string) $this->get_value($where['column']);
        if ($this->is_json_selector($column_value)) {
            [$field, $path] = $this->wrap_json_field_and_path($column_value);
            return '(json_extract(' . $field . $path . ') is not null AND json_type(json_extract(' . $field . $path . ')) != \'NULL\')';
        }
        return parent::where_not_null($query, $where);
    }
    /**
     * Compile a "where fulltext" clause.
     *
     * @param  array  $where
     */
    public function where_full_text(Builder $query, $where): string
    {
        $columns = $this->columnize($where['columns']);
        $value = $this->parameter($where['value']);
        $mode = ($where['options']['mode'] ?? []) === 'boolean' ? ' in boolean mode' : ' in natural language mode';
        $expanded = ($where['options']['expanded'] ?? []) && ($where['options']['mode'] ?? []) !== 'boolean' ? ' with query expansion' : '';
        return "match ({$columns}) against (" . $value . "{$mode}{$expanded})";
    }
    /**
     * Compile the index hints for the query.
     *
     * @param  \Illuminate\Database\Query\IndexHint  $indexHint
     *
     * @throws \InvalidArgumentException
     */
    protected function compile_index_hint(Builder $query, $index_hint): string
    {
        $index = $index_hint->index;
        $indexes = array_map(trim(...), explode(',', $index));
        foreach ($indexes as $i) {
            if (!preg_match('/^[a-zA-Z0-9_$]+$/', $i)) {
                throw new InvalidArgumentException('Index name contains invalid characters.');
            }
        }
        return match ($index_hint->type) {
            'hint' => "use index ({$index})",
            'force' => "force index ({$index})",
            default => "ignore index ({$index})",
        };
    }
    /**
     * Compile a group limit clause.
     */
    protected function compile_group_limit(Builder $query): string
    {
        return $this->use_legacy_group_limit($query) ? $this->compile_legacy_group_limit($query) : parent::compile_group_limit($query);
    }
    /**
     * Determine whether to use a legacy group limit clause for MySQL < 8.0.
     */
    public function use_legacy_group_limit(Builder $query): bool
    {
        $version = $query->get_connection()->get_server_version();
        return !$query->get_connection()->is_maria() && version_compare($version, '8.0.11', '<');
    }
    /**
     * Compile a group limit clause for MySQL < 8.0.
     *
     * Derived from https://softonsofa.com/tweaking-eloquent-relations-how-to-get-n-related-models-per-parent/.
     */
    protected function compile_legacy_group_limit(Builder $query): string
    {
        $limit = (int) $query->group_limit['value'];
        $offset = $query->offset;
        if (isset($offset)) {
            $offset = (int) $offset;
            $limit += $offset;
            $query->offset = null;
        }
        $column = last(explode('.', (string) $query->group_limit['column']));
        $column = $this->wrap($column);
        $partition = ', @laravel_row := if(@laravel_group = ' . $column . ', @laravel_row + 1, 1) as `laravel_row`';
        $partition .= ', @laravel_group := ' . $column;
        $orders = (array) $query->orders;
        array_unshift($orders, ['column' => $query->group_limit['column'], 'direction' => 'asc']);
        $query->orders = $orders;
        $components = $this->compile_components($query);
        $sql = $this->concatenate($components);
        $from = '(select @laravel_row := 0, @laravel_group := 0) as `laravel_vars`, (' . $sql . ') as `laravel_table`';
        $sql = 'select `laravel_table`.*' . $partition . ' from ' . $from . ' having `laravel_row` <= ' . $limit;
        if (isset($offset)) {
            $sql .= ' and `laravel_row` > ' . $offset;
        }
        return $sql . ' order by `laravel_row`';
    }
    /**
     * Compile an insert ignore statement into SQL.
     *
     * @return string
     */
    public function compile_insert_or_ignore(Builder $query, array $values)
    {
        return Str::replace_first('insert', 'insert ignore', $this->compile_insert($query, $values));
    }
    /**
     * Compile an insert ignore statement using a subquery into SQL.
     *
     * @return string
     */
    public function compile_insert_or_ignore_using(Builder $query, array $columns, string $sql)
    {
        return Str::replace_first('insert', 'insert ignore', $this->compile_insert_using($query, $columns, $sql));
    }
    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     */
    protected function compile_json_contains($column, $value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($column);
        return 'json_contains(' . $field . ', ' . $value . $path . ')';
    }
    /**
     * Compile a "JSON overlaps" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     */
    protected function compile_json_overlaps($column, $value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($column);
        return 'json_overlaps(' . $field . ', ' . $value . $path . ')';
    }
    /**
     * Compile a "JSON contains key" statement into SQL.
     *
     * @param  string  $column
     */
    protected function compile_json_contains_key($column): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($column);
        return 'ifnull(json_contains_path(' . $field . ', \'one\'' . $path . '), 0)';
    }
    /**
     * Compile a "JSON length" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     */
    protected function compile_json_length($column, $operator, $value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($column);
        return 'json_length(' . $field . $path . ') ' . $operator . ' ' . $value;
    }
    /**
     * Compile a "JSON value cast" statement into SQL.
     *
     * @param  string  $value
     */
    public function compile_json_value_cast($value): string
    {
        return 'cast(' . $value . ' as json)';
    }
    /**
     * Compile the random statement into SQL.
     *
     * @param  string|int  $seed
     *
     * @throws \InvalidArgumentException
     */
    public function compile_random($seed): string
    {
        if ($seed === '' || $seed === null) {
            return 'RAND()';
        }
        if (!is_numeric($seed)) {
            throw new InvalidArgumentException('The seed value must be numeric.');
        }
        return 'RAND(' . (int) $seed . ')';
    }
    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     */
    protected function compile_lock(Builder $query, $value): string
    {
        if (!is_string($value)) {
            return $value ? 'for update' : 'lock in share mode';
        }
        return $value;
    }
    /**
     * Compile an insert statement into SQL.
     */
    public function compile_insert(Builder $query, array $values): string
    {
        if (empty($values)) {
            $values = [[]];
        }
        return parent::compile_insert($query, $values);
    }
    /**
     * Compile the columns for an update statement.
     */
    protected function compile_update_columns(Builder $query, array $values): string
    {
        return (new Collection($values))->map(function ($value, $key): string {
            if ($this->is_json_selector($key)) {
                return $this->compile_json_update_column($key, $value);
            }
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        })->implode(', ');
    }
    /**
     * Compile an "upsert" statement into SQL.
     */
    public function compile_upsert(Builder $query, array $values, array $unique_by, array $update): string
    {
        $use_upsert_alias = $query->connection->get_config('use_upsert_alias');
        $sql = $this->compile_insert($query, $values);
        if ($use_upsert_alias) {
            $sql .= ' as laravel_upsert_alias';
        }
        $sql .= ' on duplicate key update ';
        $columns = (new Collection($update))->map(function ($value, $key) use ($use_upsert_alias): string {
            if (!is_numeric($key)) {
                return $this->wrap($key) . ' = ' . $this->parameter($value);
            }
            return $use_upsert_alias ? $this->wrap($value) . ' = ' . $this->wrap('laravel_upsert_alias') . '.' . $this->wrap($value) : $this->wrap($value) . ' = values(' . $this->wrap($value) . ')';
        })->implode(', ');
        return $sql . $columns;
    }
    /**
     * Compile a "lateral join" clause.
     */
    public function compile_join_lateral(Join_Lateral_Clause $join, string $expression): string
    {
        return trim("{$join->type} join lateral {$expression} on true");
    }
    /**
     * Prepare a JSON column being updated using the JSON_SET function.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function compile_json_update_column($key, $value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = 'cast(? as json)';
        } else {
            $value = $this->parameter($value);
        }
        [$field, $path] = $this->wrap_json_field_and_path($key);
        return "{$field} = json_set({$field}{$path}, {$value})";
    }
    /**
     * Compile an update statement without joins into SQL.
     *
     * @param  string  $table
     * @param  string  $columns
     * @param  string  $where
     */
    protected function compile_update_without_joins(Builder $query, $table, $columns, $where): string
    {
        $sql = parent::compile_update_without_joins($query, $table, $columns, $where);
        if (!empty($query->orders)) {
            $sql .= ' ' . $this->compile_orders($query, $query->orders);
        }
        if (isset($query->limit)) {
            $sql .= ' ' . $this->compile_limit($query, $query->limit);
        }
        return $sql;
    }
    /**
     * Prepare the bindings for an update statement.
     *
     * Booleans, integers, and doubles are inserted into JSON updates as raw values.
     */
    #[\Override]
    public function prepare_bindings_for_update(array $bindings, array $values): array
    {
        $values = (new Collection($values))->reject(fn($value, $column): bool => $this->is_json_selector($column) && is_bool($value))->map(fn($value) => is_array($value) ? json_encode($value) : $value)->all();
        return parent::prepare_bindings_for_update($bindings, $values);
    }
    /**
     * Compile a delete query that does not use joins.
     *
     * @param  string  $table
     * @param  string  $where
     */
    protected function compile_delete_without_joins(Builder $query, $table, $where): string
    {
        $sql = parent::compile_delete_without_joins($query, $table, $where);
        // When using MySQL, delete statements may contain order by statements and limits
        // so we will compile both of those here. Once we have finished compiling this
        // we will return the completed SQL statement so it will be executed for us.
        if (!empty($query->orders)) {
            $sql .= ' ' . $this->compile_orders($query, $query->orders);
        }
        if (isset($query->limit)) {
            $sql .= ' ' . $this->compile_limit($query, $query->limit);
        }
        return $sql;
    }
    /**
     * Compile a query to get the number of open connections for a database.
     */
    public function compile_thread_count(): string
    {
        return 'select variable_value as `Value` from performance_schema.session_status where variable_name = \'threads_connected\'';
    }
    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     */
    protected function wrap_value($value): string
    {
        return $value === '*' ? $value : '`' . str_replace('`', '``', $value) . '`';
    }
    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrap_json_selector($value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($value);
        return 'json_unquote(json_extract(' . $field . $path . '))';
    }
    /**
     * Wrap the given JSON selector for boolean values.
     *
     * @param  string  $value
     */
    protected function wrap_json_boolean_selector($value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($value);
        return 'json_extract(' . $field . $path . ')';
    }
}