<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Join_Lateral_Clause;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
class Sql_Server_Grammar extends Grammar
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    protected $operators = ['=', '<', '>', '<=', '>=', '!<', '!>', '<>', '!=', 'like', 'not like', 'ilike', '&', '&=', '|', '|=', '^', '^='];
    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected $select_components = ['aggregate', 'columns', 'from', 'indexHint', 'joins', 'wheres', 'groups', 'havings', 'orders', 'offset', 'limit', 'lock'];
    /**
     * Compile a select query into SQL.
     *
     * @return string
     */
    public function compile_select(Builder $query)
    {
        // An order by clause is required for SQL Server offset to function...
        if ($query->offset && empty($query->orders)) {
            $query->orders[] = ['sql' => '(SELECT 0)'];
        }
        return parent::compile_select($query);
    }
    /**
     * Compile the "select *" portion of the query.
     *
     * @param  array  $columns
     * @return string|null
     */
    protected function compile_columns(Builder $query, $columns)
    {
        if (!is_null($query->aggregate)) {
            return;
        }
        $select = $query->distinct ? 'select distinct ' : 'select ';
        // If there is a limit on the query, but not an offset, we will add the top
        // clause to the query, which serves as a "limit" type clause within the
        // SQL Server system similar to the limit keywords available in MySQL.
        if (is_numeric($query->limit) && $query->limit > 0 && $query->offset <= 0) {
            $select .= 'top ' . (int) $query->limit . ' ';
        }
        return $select . $this->columnize($columns);
    }
    /**
     * Compile the "from" portion of the query.
     *
     * @param  string  $table
     */
    protected function compile_from(Builder $query, $table): string
    {
        $from = parent::compile_from($query, $table);
        if (is_string($query->lock)) {
            return $from . ' ' . $query->lock;
        }
        if (!is_null($query->lock)) {
            return $from . ' with(rowlock,' . ($query->lock ? 'updlock,' : '') . 'holdlock)';
        }
        return $from;
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
        return "with (index([{$index}]))";
    }
    /**
     * {@inheritdoc}
     *
     * @param  array  $where
     */
    protected function where_bitwise(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);
        $operator = str_replace('?', '??', $where['operator']);
        return '(' . $this->wrap($where['column']) . ' ' . $operator . ' ' . $value . ') != 0';
    }
    /**
     * Compile a "where null safe equals" clause.
     *
     * @param  array  $where
     */
    protected function where_null_safe_equals(Builder $query, $where): string
    {
        return 'exists (select ' . $this->wrap($where['column']) . ' intersect select ' . $this->parameter($where['value']) . ')';
    }
    /**
     * Compile a "where date" clause.
     *
     * @param  array  $where
     */
    protected function where_date(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);
        return 'cast(' . $this->wrap($where['column']) . ' as date) ' . $where['operator'] . ' ' . $value;
    }
    /**
     * Compile a "where time" clause.
     *
     * @param  array  $where
     */
    protected function where_time(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);
        return 'cast(' . $this->wrap($where['column']) . ' as time) ' . $where['operator'] . ' ' . $value;
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
        return $value . ' in (select [value] from openjson(' . $field . $path . '))';
    }
    /**
     * Prepare the binding for a "JSON contains" statement.
     *
     * @param  mixed  $binding
     * @return string
     */
    public function prepare_binding_for_json_contains($binding)
    {
        return is_bool($binding) ? json_encode($binding) : $binding;
    }
    /**
     * Compile a "JSON contains key" statement into SQL.
     *
     * @param  string  $column
     */
    protected function compile_json_contains_key($column): string
    {
        $segments = explode('->', $column);
        $last_segment = array_pop($segments);
        if (preg_match('/\[([0-9]+)\]$/', $last_segment, $matches)) {
            $segments[] = Str::before_last($last_segment, $matches[0]);
            $key = $matches[1];
        } else {
            $key = "'" . str_replace("'", "''", $last_segment) . "'";
        }
        [$field, $path] = $this->wrap_json_field_and_path(implode('->', $segments));
        return $key . ' in (select [key] from openjson(' . $field . $path . '))';
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
        return '(select count(*) from openjson(' . $field . $path . ')) ' . $operator . ' ' . $value;
    }
    /**
     * Compile a "JSON value cast" statement into SQL.
     *
     * @param  string  $value
     */
    public function compile_json_value_cast($value): string
    {
        return 'json_query(' . $value . ')';
    }
    /**
     * Compile a single having clause.
     *
     * @return string
     */
    protected function compile_having(array $having)
    {
        if ($having['type'] === 'Bitwise') {
            return $this->compile_having_bitwise($having);
        }
        return parent::compile_having($having);
    }
    /**
     * Compile a having clause involving a bitwise operator.
     */
    protected function compile_having_bitwise(array $having): string
    {
        $column = $this->wrap($having['column']);
        $parameter = $this->parameter($having['value']);
        return '(' . $column . ' ' . $having['operator'] . ' ' . $parameter . ') != 0';
    }
    /**
     * Compile a delete statement without joins into SQL.
     *
     * @param  string  $table
     * @param  string  $where
     */
    protected function compile_delete_without_joins(Builder $query, $table, $where): string
    {
        $sql = parent::compile_delete_without_joins($query, $table, $where);
        return !is_null($query->limit) && $query->limit > 0 && $query->offset <= 0 ? Str::replace_first('delete', 'delete top (' . $query->limit . ')', $sql) : $sql;
    }
    /**
     * Compile the random statement into SQL.
     *
     * @param  string|int  $seed
     */
    public function compile_random($seed): string
    {
        return 'NEWID()';
    }
    /**
     * Compile the "limit" portions of the query.
     *
     * @param  int  $limit
     */
    protected function compile_limit(Builder $query, $limit): string
    {
        $limit = (int) $limit;
        if ($limit && $query->offset > 0) {
            return "fetch next {$limit} rows only";
        }
        return '';
    }
    /**
     * Compile a row number clause.
     *
     * @param  string  $partition
     */
    protected function compile_row_number($partition, string $orders): string
    {
        if (empty($orders)) {
            $orders = 'order by (select 0)';
        }
        return parent::compile_row_number($partition, $orders);
    }
    /**
     * Compile the "offset" portions of the query.
     *
     * @param  int  $offset
     */
    protected function compile_offset(Builder $query, $offset): string
    {
        $offset = (int) $offset;
        if ($offset) {
            return "offset {$offset} rows";
        }
        return '';
    }
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
        return 'select * from (' . $sql . ') as ' . $this->wrap_table('temp_table');
    }
    /**
     * Compile an exists statement into SQL.
     */
    public function compile_exists(Builder $query): string
    {
        $exists_query = clone $query;
        $exists_query->columns = [];
        return $this->compile_select($exists_query->select_raw('1 [exists]')->limit(1));
    }
    /**
     * Compile an update statement with joins into SQL.
     *
     * @param  string  $table
     * @param  string  $columns
     * @param  string  $where
     */
    protected function compile_update_with_joins(Builder $query, $table, $columns, $where): string
    {
        $alias = last(explode(' as ', $table));
        $joins = $this->compile_joins($query, $query->joins);
        return "update {$alias} set {$columns} from {$table} {$joins} {$where}";
    }
    /**
     * Compile an "upsert" statement into SQL.
     */
    public function compile_upsert(Builder $query, array $values, array $unique_by, array $update): string
    {
        $columns = $this->columnize(array_keys(array_first($values)));
        $sql = 'merge ' . $this->wrap_table($query->from) . ' ';
        $parameters = (new Collection($values))->map(fn(array $record): string => '(' . $this->parameterize($record) . ')')->implode(', ');
        $sql .= 'using (values ' . $parameters . ') ' . $this->wrap_table('laravel_source') . ' (' . $columns . ') ';
        $on = (new Collection($unique_by))->map(fn($column): string => $this->wrap('laravel_source.' . $column) . ' = ' . $this->wrap($query->from . '.' . $column))->implode(' and ');
        $sql .= 'on ' . $on . ' ';
        if ($update) {
            $update = (new Collection($update))->map(fn($value, $key): string => is_numeric($key) ? $this->wrap($value) . ' = ' . $this->wrap('laravel_source.' . $value) : $this->wrap($key) . ' = ' . $this->parameter($value))->implode(', ');
            $sql .= 'when matched then update set ' . $update . ' ';
        }
        return $sql . ('when not matched then insert (' . $columns . ') values (' . $columns . ');');
    }
    /**
     * Prepare the bindings for an update statement.
     */
    #[\Override]
    public function prepare_bindings_for_update(array $bindings, array $values): array
    {
        $clean_bindings = Arr::except($bindings, 'select');
        $values = Arr::flatten(array_map(fn($value) => value($value), $values));
        return array_values(array_merge($values, Arr::flatten($clean_bindings)));
    }
    /**
     * Compile a "lateral join" clause.
     */
    public function compile_join_lateral(Join_Lateral_Clause $join, string $expression): string
    {
        $type = $join->type == 'left' ? 'outer' : 'cross';
        return trim("{$type} apply {$expression}");
    }
    /**
     * Compile the SQL statement to define a savepoint.
     *
     * @param  string  $name
     */
    public function compile_savepoint($name): string
    {
        return 'SAVE TRANSACTION ' . $name;
    }
    /**
     * Compile the SQL statement to execute a savepoint rollback.
     *
     * @param  string  $name
     */
    public function compile_savepoint_roll_back($name): string
    {
        return 'ROLLBACK TRANSACTION ' . $name;
    }
    /**
     * Compile a query to get the number of open connections for a database.
     */
    public function compile_thread_count(): string
    {
        return 'select count(*) Value from sys.dm_exec_sessions where status = N\'running\'';
    }
    /**
     * Get the format for database stored dates.
     */
    public function get_date_format(): string
    {
        return 'Y-m-d H:i:s.v';
    }
    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     */
    protected function wrap_value($value): string
    {
        return $value === '*' ? $value : '[' . str_replace(']', ']]', $value) . ']';
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
    /**
     * Wrap the given JSON boolean value.
     *
     * @param  string  $value
     */
    protected function wrap_json_boolean_value($value): string
    {
        return "'" . $value . "'";
    }
    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  string|null  $prefix
     * @return string
     */
    public function wrap_table($table, $prefix = null)
    {
        if (!$this->is_expression($table)) {
            return $this->wrap_table_valued_function(parent::wrap_table($table, $prefix));
        }
        return $this->get_value($table);
    }
    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  string  $table
     * @return string
     */
    protected function wrap_table_valued_function($table)
    {
        if (preg_match('/^(.+?)(\(.*?\))]$/', $table, $matches) === 1) {
            return $matches[1] . ']' . $matches[2];
        }
        return $table;
    }
}