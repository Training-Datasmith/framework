<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
class Sq_Lite_Grammar extends Grammar
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    protected $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like', 'ilike', '&', '|', '<<', '>>'];
    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     */
    protected function compile_lock(Builder $query, $value): string
    {
        return '';
    }
    /**
     * Wrap a union subquery in parentheses.
     *
     * @param  string  $sql
     */
    protected function wrap_union($sql): string
    {
        return 'select * from (' . $sql . ')';
    }
    /**
     * Compile a "where like" clause.
     *
     * @return string
     */
    protected function where_like(Builder $query, array $where)
    {
        if ($where['caseSensitive'] == false) {
            return parent::where_like($query, $where);
        }
        $where['operator'] = $where['not'] ? 'not glob' : 'glob';
        return $this->where_basic($query, $where);
    }
    /**
     * Convert a LIKE pattern to a GLOB pattern using simple string replacement.
     *
     * @param  string  $value
     * @param  bool  $caseSensitive
     * @return string
     */
    public function prepare_where_like_binding($value, $case_sensitive)
    {
        return $case_sensitive === false ? $value : str_replace(['*', '?', '%', '_'], ['[*]', '[?]', '*', '?'], $value);
    }
    /**
     * Compile a "where null safe equals" clause.
     *
     * @param  array  $where
     */
    protected function where_null_safe_equals(Builder $query, $where): string
    {
        return $this->wrap($where['column']) . ' is ' . $this->parameter($where['value']);
    }
    /**
     * Compile a "where date" clause.
     *
     * @param  array  $where
     */
    protected function where_date(Builder $query, $where): string
    {
        return $this->date_based_where('%Y-%m-%d', $query, $where);
    }
    /**
     * Compile a "where day" clause.
     *
     * @param  array  $where
     */
    protected function where_day(Builder $query, $where): string
    {
        return $this->date_based_where('%d', $query, $where);
    }
    /**
     * Compile a "where month" clause.
     *
     * @param  array  $where
     */
    protected function where_month(Builder $query, $where): string
    {
        return $this->date_based_where('%m', $query, $where);
    }
    /**
     * Compile a "where year" clause.
     *
     * @param  array  $where
     */
    protected function where_year(Builder $query, $where): string
    {
        return $this->date_based_where('%Y', $query, $where);
    }
    /**
     * Compile a "where time" clause.
     *
     * @param  array  $where
     */
    protected function where_time(Builder $query, $where): string
    {
        return $this->date_based_where('%H:%M:%S', $query, $where);
    }
    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  array  $where
     */
    protected function date_based_where($type, Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);
        return "strftime('{$type}', {$this->wrap($where['column'])}) {$where['operator']} cast({$value} as text)";
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
        if ($index_hint->type !== 'force') {
            return '';
        }
        $index = $index_hint->index;
        if (!preg_match('/^[a-zA-Z0-9_$]+$/', $index)) {
            throw new InvalidArgumentException('Index name contains invalid characters.');
        }
        return "indexed by {$index}";
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
        return 'json_array_length(' . $field . $path . ') ' . $operator . ' ' . $value;
    }
    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param  string  $column
     * @param  mixed  $value
     */
    protected function compile_json_contains($column, $value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($column);
        return 'exists (select 1 from json_each(' . $field . $path . ') where ' . $this->wrap('json_each.value') . ' is ' . $value . ')';
    }
    /**
     * Prepare the binding for a "JSON contains" statement.
     *
     * @param  mixed  $binding
     * @return mixed
     */
    public function prepare_binding_for_json_contains($binding)
    {
        return $binding;
    }
    /**
     * Compile a "JSON contains key" statement into SQL.
     *
     * @param  string  $column
     */
    protected function compile_json_contains_key($column): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($column);
        return 'json_type(' . $field . $path . ') is not null';
    }
    /**
     * Compile a group limit clause.
     */
    protected function compile_group_limit(Builder $query): string
    {
        $version = $query->get_connection()->get_server_version();
        if (version_compare($version, '3.25.0', '>=')) {
            return parent::compile_group_limit($query);
        }
        $query->group_limit = null;
        return $this->compile_select($query);
    }
    /**
     * Compile an update statement into SQL.
     */
    public function compile_update(Builder $query, array $values): string
    {
        if (isset($query->joins) || isset($query->limit)) {
            return $this->compile_update_with_joins_or_limit($query, $values);
        }
        return parent::compile_update($query, $values);
    }
    /**
     * Compile an insert ignore statement into SQL.
     *
     * @return string
     */
    public function compile_insert_or_ignore(Builder $query, array $values)
    {
        return Str::replace_first('insert', 'insert or ignore', $this->compile_insert($query, $values));
    }
    /**
     * Compile an insert ignore statement using a subquery into SQL.
     *
     * @return string
     */
    public function compile_insert_or_ignore_using(Builder $query, array $columns, string $sql)
    {
        return Str::replace_first('insert', 'insert or ignore', $this->compile_insert_using($query, $columns, $sql));
    }
    /**
     * Compile the columns for an update statement.
     */
    protected function compile_update_columns(Builder $query, array $values): string
    {
        $json_groups = $this->group_json_columns_for_update($values);
        return (new Collection($values))->reject(fn($value, $key) => $this->is_json_selector($key))->merge($json_groups)->map(function ($value, $key) use ($json_groups): string {
            $column = last(explode('.', $key));
            $value = isset($json_groups[$key]) ? $this->compile_json_patch($column, $value) : $this->parameter($value);
            return $this->wrap($column) . ' = ' . $value;
        })->implode(', ');
    }
    /**
     * Compile an "upsert" statement into SQL.
     */
    public function compile_upsert(Builder $query, array $values, array $unique_by, array $update): string
    {
        $sql = $this->compile_insert($query, $values);
        $sql .= ' on conflict (' . $this->columnize($unique_by) . ') do update set ';
        $columns = (new Collection($update))->map(fn($value, $key): string => is_numeric($key) ? $this->wrap($value) . ' = ' . $this->wrap_value('excluded') . '.' . $this->wrap($value) : $this->wrap($key) . ' = ' . $this->parameter($value))->implode(', ');
        return $sql . $columns;
    }
    /**
     * Group the nested JSON columns.
     */
    protected function group_json_columns_for_update(array $values): array
    {
        $groups = [];
        foreach ($values as $key => $value) {
            if ($this->is_json_selector($key)) {
                Arr::set($groups, str_replace('->', '.', Str::after($key, '.')), $value);
            }
        }
        return $groups;
    }
    /**
     * Compile a "JSON" patch statement into SQL.
     *
     * @param  string  $column
     * @param  mixed  $value
     */
    protected function compile_json_patch($column, $value): string
    {
        return "json_patch(ifnull({$this->wrap($column)}, json('{}')), json({$this->parameter($value)}))";
    }
    /**
     * Compile an update statement with joins or limit into SQL.
     */
    protected function compile_update_with_joins_or_limit(Builder $query, array $values): string
    {
        $table = $this->wrap_table($query->from);
        $columns = $this->compile_update_columns($query, $values);
        $alias = last(preg_split('/\s+as\s+/i', $query->from));
        $select_sql = $this->compile_select($query->select($alias . '.rowid'));
        return "update {$table} set {$columns} where {$this->wrap('rowid')} in ({$select_sql})";
    }
    /**
     * Prepare the bindings for an update statement.
     */
    #[\Override]
    public function prepare_bindings_for_update(array $bindings, array $values): array
    {
        $groups = $this->group_json_columns_for_update($values);
        $values = (new Collection($values))->reject(fn($value, $key) => $this->is_json_selector($key))->merge($groups)->map(fn($value) => is_array($value) ? json_encode($value) : $value)->all();
        $clean_bindings = Arr::except($bindings, 'select');
        $values = Arr::flatten(array_map(fn($value) => value($value), $values));
        return array_values(array_merge($values, Arr::flatten($clean_bindings)));
    }
    /**
     * Compile a delete statement into SQL.
     */
    public function compile_delete(Builder $query): string
    {
        if (isset($query->joins) || isset($query->limit)) {
            return $this->compile_delete_with_joins_or_limit($query);
        }
        return parent::compile_delete($query);
    }
    /**
     * Compile a delete statement with joins or limit into SQL.
     */
    protected function compile_delete_with_joins_or_limit(Builder $query): string
    {
        $table = $this->wrap_table($query->from);
        $alias = last(preg_split('/\s+as\s+/i', $query->from));
        $select_sql = $this->compile_select($query->select($alias . '.rowid'));
        return "delete from {$table} where {$this->wrap('rowid')} in ({$select_sql})";
    }
    /**
     * Compile a truncate table statement into SQL.
     */
    public function compile_truncate(Builder $query): array
    {
        [$schema, $table] = $query->get_connection()->get_schema_builder()->parse_schema_and_table($query->from);
        $schema = $schema ? $this->wrap_value($schema) . '.' : '';
        return ['delete from ' . $schema . 'sqlite_sequence where name = ?' => [$query->get_connection()->get_table_prefix() . $table], 'delete from ' . $this->wrap_table($query->from) => []];
    }
    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrap_json_selector($value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($value);
        return 'json_extract(' . $field . $path . ')';
    }
}