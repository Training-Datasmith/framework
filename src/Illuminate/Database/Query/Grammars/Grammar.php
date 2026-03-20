<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Grammars;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Concerns\Compiles_Json_Paths;
use Illuminate\Database\Grammar as BaseGrammar;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Join_Clause;
use Illuminate\Database\Query\Join_Lateral_Clause;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;
class Grammar extends Base_Grammar
{
    use Compiles_Json_Paths;
    /**
     * The grammar specific operators.
     *
     * @var array
     */
    protected $operators = [];
    /**
     * The grammar specific bitwise operators.
     *
     * @var array
     */
    protected $bitwise_operators = [];
    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected $select_components = ['aggregate', 'columns', 'from', 'indexHint', 'joins', 'wheres', 'groups', 'havings', 'orders', 'limit', 'offset', 'lock'];
    /**
     * Compile a select query into SQL.
     */
    public function compile_select(Builder $query): string
    {
        if (($query->unions || $query->havings) && $query->aggregate) {
            return $this->compile_union_aggregate($query);
        }
        // If a "group limit" is in place, we will need to compile the SQL to use a
        // different syntax. This primarily supports limits on eager loads using
        // Eloquent. We'll also set the columns if they have not been defined.
        if (isset($query->group_limit)) {
            if (is_null($query->columns)) {
                $query->columns = ['*'];
            }
            return $this->compile_group_limit($query);
        }
        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;
        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }
        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the SQL.
        $sql = trim($this->concatenate($this->compile_components($query)));
        if ($query->unions) {
            $sql = $this->wrap_union($sql) . ' ' . $this->compile_unions($query);
        }
        $query->columns = $original;
        return $sql;
    }
    /**
     * Compile the components necessary for a select clause.
     */
    protected function compile_components(Builder $query): array
    {
        $sql = [];
        foreach ($this->select_components as $component) {
            if (isset($query->{$component})) {
                $method = 'compile' . ucfirst($component);
                $sql[$component] = $this->{$method}($query, $query->{$component});
            }
        }
        return $sql;
    }
    /**
     * Compile an aggregated select clause.
     *
     * @param  array{function: string, columns: array<\Illuminate\Contracts\Database\Query\Expression|string>}  $aggregate
     */
    protected function compile_aggregate(Builder $query, $aggregate): string
    {
        $column = $this->columnize($aggregate['columns']);
        // If the query has a "distinct" constraint and we're not asking for all columns
        // we need to prepend "distinct" onto the column name so that the query takes
        // it into account when it performs the aggregating operations on the data.
        if (is_array($query->distinct)) {
            $column = 'distinct ' . $this->columnize($query->distinct);
        } elseif ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }
        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }
    /**
     * Compile the "select *" portion of the query.
     *
     * @return string|null
     */
    protected function compile_columns(Builder $query, array $columns)
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (!is_null($query->aggregate)) {
            return;
        }
        if ($query->distinct) {
            $select = 'select distinct ';
        } else {
            $select = 'select ';
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
        return 'from ' . $this->wrap_table($table);
    }
    /**
     * Compile the "join" portions of the query.
     *
     * @param  array  $joins
     */
    protected function compile_joins(Builder $query, $joins): string
    {
        return (new Collection($joins))->map(function ($join) use ($query): string {
            $table = $this->wrap_table($join->table);
            $nested_joins = is_null($join->joins) ? '' : ' ' . $this->compile_joins($query, $join->joins);
            $table_and_nested_joins = is_null($join->joins) ? $table : '(' . $table . $nested_joins . ')';
            if ($join instanceof Join_Lateral_Clause) {
                return $this->compile_join_lateral($join, $table_and_nested_joins);
            }
            return trim("{$join->type} join {$table_and_nested_joins} {$this->compile_wheres($join)}");
        })->implode(' ');
    }
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
     * Compile the "where" portions of the query.
     */
    public function compile_wheres(Builder $query): string
    {
        // Each type of where clause has its own compiler function, which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (is_null($query->wheres)) {
            return '';
        }
        // If we actually have some where clauses, we will strip off the first boolean
        // operator, which is added by the query builders for convenience so we can
        // avoid checking for the first clauses in each of the compilers methods.
        if (count($sql = $this->compile_wheres_to_array($query)) > 0) {
            return $this->concatenate_where_clauses($query, $sql);
        }
        return '';
    }
    /**
     * Get an array of all the where clauses for the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array
     */
    protected function compile_wheres_to_array($query)
    {
        return (new Collection($query->wheres))->map(fn($where): string => $where['boolean'] . ' ' . $this->{"where{$where['type']}"}($query, $where))->all();
    }
    /**
     * Format the where clause statements into one string.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $sql
     */
    protected function concatenate_where_clauses($query, $sql): string
    {
        $conjunction = $query instanceof Join_Clause ? 'on' : 'where';
        return $conjunction . ' ' . $this->remove_leading_boolean(implode(' ', $sql));
    }
    /**
     * Compile a raw where clause.
     *
     * @return string
     */
    protected function where_raw(Builder $query, array $where)
    {
        return $where['sql'] instanceof Expression ? $where['sql']->get_value($this) : $where['sql'];
    }
    /**
     * Compile a basic where clause.
     */
    protected function where_basic(Builder $query, array $where): string
    {
        $value = $this->parameter($where['value']);
        $operator = str_replace('?', '??', $where['operator']);
        return $this->wrap($where['column']) . ' ' . $operator . ' ' . $value;
    }
    /**
     * Compile a bitwise operator where clause.
     */
    protected function where_bitwise(Builder $query, array $where): string
    {
        return $this->where_basic($query, $where);
    }
    /**
     * Compile a "where like" clause.
     */
    protected function where_like(Builder $query, array $where): string
    {
        if ($where['caseSensitive']) {
            throw new RuntimeException('This database engine does not support case sensitive like operations.');
        }
        $where['operator'] = $where['not'] ? 'not like' : 'like';
        return $this->where_basic($query, $where);
    }
    /**
     * Compile a "where null safe equals" clause.
     */
    protected function where_null_safe_equals(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is not distinct from ' . $this->parameter($where['value']);
    }
    /**
     * Compile a "where in" clause.
     */
    protected function where_in(Builder $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . $this->parameterize($where['values']) . ')';
        }
        return '0 = 1';
    }
    /**
     * Compile a "where not in" clause.
     */
    protected function where_not_in(Builder $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . $this->parameterize($where['values']) . ')';
        }
        return '1 = 1';
    }
    /**
     * Compile a "where not in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     */
    protected function where_not_in_raw(Builder $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . implode(', ', $where['values']) . ')';
        }
        return '1 = 1';
    }
    /**
     * Compile a "where in raw" clause.
     *
     * For safety, whereIntegerInRaw ensures this method is only used with integer values.
     */
    protected function where_in_raw(Builder $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . implode(', ', $where['values']) . ')';
        }
        return '0 = 1';
    }
    /**
     * Compile a "where null" clause.
     */
    protected function where_null(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is null';
    }
    /**
     * Compile a "where not null" clause.
     */
    protected function where_not_null(Builder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is not null';
    }
    /**
     * Compile a "between" where clause.
     */
    protected function where_between(Builder $query, array $where): string
    {
        $between = $where['not'] ? 'not between' : 'between';
        $min = $this->parameter(is_array($where['values']) ? array_first($where['values']) : $where['values'][0]);
        $max = $this->parameter(is_array($where['values']) ? array_last($where['values']) : $where['values'][1]);
        return $this->wrap($where['column']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }
    /**
     * Compile a "between" where clause.
     */
    protected function where_between_columns(Builder $query, array $where): string
    {
        $between = $where['not'] ? 'not between' : 'between';
        $min = $this->wrap(is_array($where['values']) ? array_first($where['values']) : $where['values'][0]);
        $max = $this->wrap(is_array($where['values']) ? array_last($where['values']) : $where['values'][1]);
        return $this->wrap($where['column']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }
    /**
     * Compile a "value between" where clause.
     */
    protected function where_value_between(Builder $query, array $where): string
    {
        $between = $where['not'] ? 'not between' : 'between';
        $min = $this->wrap(is_array($where['columns']) ? array_first($where['columns']) : $where['columns'][0]);
        $max = $this->wrap(is_array($where['columns']) ? array_last($where['columns']) : $where['columns'][1]);
        return $this->parameter($where['value']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }
    /**
     * Compile a "where date" clause.
     */
    protected function where_date(Builder $query, array $where): string
    {
        return $this->date_based_where('date', $query, $where);
    }
    /**
     * Compile a "where time" clause.
     */
    protected function where_time(Builder $query, array $where): string
    {
        return $this->date_based_where('time', $query, $where);
    }
    /**
     * Compile a "where day" clause.
     */
    protected function where_day(Builder $query, array $where): string
    {
        return $this->date_based_where('day', $query, $where);
    }
    /**
     * Compile a "where month" clause.
     */
    protected function where_month(Builder $query, array $where): string
    {
        return $this->date_based_where('month', $query, $where);
    }
    /**
     * Compile a "where year" clause.
     */
    protected function where_year(Builder $query, array $where): string
    {
        return $this->date_based_where('year', $query, $where);
    }
    /**
     * Compile a date based where clause.
     */
    protected function date_based_where(string $type, Builder $query, array $where): string
    {
        $value = $this->parameter($where['value']);
        return $type . '(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
    }
    /**
     * Compile a where clause comparing two columns.
     */
    protected function where_column(Builder $query, array $where): string
    {
        return $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
    }
    /**
     * Compile a nested where clause.
     */
    protected function where_nested(Builder $query, array $where): string
    {
        // Here we will calculate what portion of the string we need to remove. If this
        // is a join clause query, we need to remove the "on" portion of the SQL and
        // if it is a normal query we need to take the leading "where" of queries.
        $offset = $where['query'] instanceof Join_Clause ? 3 : 6;
        return '(' . substr($this->compile_wheres($where['query']), $offset) . ')';
    }
    /**
     * Compile a where condition with a sub-select.
     */
    protected function where_sub(Builder $query, array $where): string
    {
        $select = $this->compile_select($where['query']);
        return $this->wrap($where['column']) . ' ' . $where['operator'] . " ({$select})";
    }
    /**
     * Compile a where exists clause.
     */
    protected function where_exists(Builder $query, array $where): string
    {
        return 'exists (' . $this->compile_select($where['query']) . ')';
    }
    /**
     * Compile a where exists clause.
     */
    protected function where_not_exists(Builder $query, array $where): string
    {
        return 'not exists (' . $this->compile_select($where['query']) . ')';
    }
    /**
     * Compile a where row values condition.
     */
    protected function where_row_values(Builder $query, array $where): string
    {
        $columns = $this->columnize($where['columns']);
        $values = $this->parameterize($where['values']);
        return '(' . $columns . ') ' . $where['operator'] . ' (' . $values . ')';
    }
    /**
     * Compile a "where JSON boolean" clause.
     */
    protected function where_json_boolean(Builder $query, array $where): string
    {
        $column = $this->wrap_json_boolean_selector($where['column']);
        $value = $this->wrap_json_boolean_value($this->parameter($where['value']));
        return $column . ' ' . $where['operator'] . ' ' . $value;
    }
    /**
     * Compile a "where JSON contains" clause.
     */
    protected function where_json_contains(Builder $query, array $where): string
    {
        $not = $where['not'] ? 'not ' : '';
        return $not . $this->compile_json_contains($where['column'], $this->parameter($where['value']));
    }
    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     *
     * @throws \RuntimeException
     */
    protected function compile_json_contains($column, $value): never
    {
        throw new RuntimeException('This database engine does not support JSON contains operations.');
    }
    /**
     * Compile a "where JSON overlaps" clause.
     */
    protected function where_json_overlaps(Builder $query, array $where): string
    {
        $not = $where['not'] ? 'not ' : '';
        return $not . $this->compile_json_overlaps($where['column'], $this->parameter($where['value']));
    }
    /**
     * Compile a "JSON overlaps" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     *
     * @throws \RuntimeException
     */
    protected function compile_json_overlaps($column, $value): never
    {
        throw new RuntimeException('This database engine does not support JSON overlaps operations.');
    }
    /**
     * Prepare the binding for a "JSON contains" statement.
     *
     * @param  mixed  $binding
     * @return string
     */
    public function prepare_binding_for_json_contains($binding)
    {
        return json_encode($binding, JSON_UNESCAPED_UNICODE);
    }
    /**
     * Compile a "where JSON contains key" clause.
     */
    protected function where_json_contains_key(Builder $query, array $where): string
    {
        $not = $where['not'] ? 'not ' : '';
        return $not . $this->compile_json_contains_key($where['column']);
    }
    /**
     * Compile a "JSON contains key" statement into SQL.
     *
     * @param  string  $column
     *
     * @throws \RuntimeException
     */
    protected function compile_json_contains_key($column): never
    {
        throw new RuntimeException('This database engine does not support JSON contains key operations.');
    }
    /**
     * Compile a "where JSON length" clause.
     *
     * @return string
     */
    protected function where_json_length(Builder $query, array $where)
    {
        return $this->compile_json_length($where['column'], $where['operator'], $this->parameter($where['value']));
    }
    /**
     * Compile a "JSON length" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     *
     * @throws \RuntimeException
     */
    protected function compile_json_length($column, $operator, $value): never
    {
        throw new RuntimeException('This database engine does not support JSON length operations.');
    }
    /**
     * Compile a "JSON value cast" statement into SQL.
     *
     * @param  string  $value
     * @return string
     */
    public function compile_json_value_cast($value)
    {
        return $value;
    }
    /**
     * Compile a "where fulltext" clause.
     *
     * @param  array  $where
     */
    public function where_full_text(Builder $query, $where): never
    {
        throw new RuntimeException('This database engine does not support fulltext search operations.');
    }
    /**
     * Compile a clause based on an expression.
     *
     * @return string
     */
    public function where_expression(Builder $query, array $where)
    {
        return $where['column']->get_value($this);
    }
    /**
     * Compile the "group by" portions of the query.
     */
    protected function compile_groups(Builder $query, array $groups): string
    {
        return 'group by ' . $this->columnize($groups);
    }
    /**
     * Compile the "having" portions of the query.
     */
    protected function compile_havings(Builder $query): string
    {
        return 'having ' . $this->remove_leading_boolean((new Collection($query->havings))->map(fn(array $having): string => $having['boolean'] . ' ' . $this->compile_having($having))->implode(' '));
    }
    /**
     * Compile a single having clause.
     *
     * @return string
     */
    protected function compile_having(array $having)
    {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        return match ($having['type']) {
            'Raw' => $having['sql'],
            'between' => $this->compile_having_between($having),
            'Null' => $this->compile_having_null($having),
            'NotNull' => $this->compile_having_not_null($having),
            'bit' => $this->compile_having_bit($having),
            'Expression' => $this->compile_having_expression($having),
            'Nested' => $this->compile_nested_havings($having),
            default => $this->compile_basic_having($having),
        };
    }
    /**
     * Compile a basic having clause.
     */
    protected function compile_basic_having(array $having): string
    {
        $column = $this->wrap($having['column']);
        $parameter = $this->parameter($having['value']);
        return $column . ' ' . $having['operator'] . ' ' . $parameter;
    }
    /**
     * Compile a "between" having clause.
     */
    protected function compile_having_between(array $having): string
    {
        $between = $having['not'] ? 'not between' : 'between';
        $column = $this->wrap($having['column']);
        $min = $this->parameter(head($having['values']));
        $max = $this->parameter(last($having['values']));
        return $column . ' ' . $between . ' ' . $min . ' and ' . $max;
    }
    /**
     * Compile a having null clause.
     */
    protected function compile_having_null(array $having): string
    {
        $column = $this->wrap($having['column']);
        return $column . ' is null';
    }
    /**
     * Compile a having not null clause.
     */
    protected function compile_having_not_null(array $having): string
    {
        $column = $this->wrap($having['column']);
        return $column . ' is not null';
    }
    /**
     * Compile a having clause involving a bit operator.
     */
    protected function compile_having_bit(array $having): string
    {
        $column = $this->wrap($having['column']);
        $parameter = $this->parameter($having['value']);
        return '(' . $column . ' ' . $having['operator'] . ' ' . $parameter . ') != 0';
    }
    /**
     * Compile a having clause involving an expression.
     *
     * @return string
     */
    protected function compile_having_expression(array $having)
    {
        return $having['column']->get_value($this);
    }
    /**
     * Compile a nested having clause.
     */
    protected function compile_nested_havings(array $having): string
    {
        return '(' . substr($this->compile_havings($having['query']), 7) . ')';
    }
    /**
     * Compile the "order by" portions of the query.
     *
     * @param  array  $orders
     */
    protected function compile_orders(Builder $query, $orders): string
    {
        if (!empty($orders)) {
            return 'order by ' . implode(', ', $this->compile_orders_to_array($query, $orders));
        }
        return '';
    }
    /**
     * Compile the query orders to an array.
     *
     * @param  array  $orders
     */
    protected function compile_orders_to_array(Builder $query, $orders): array
    {
        return array_map(function (array $order) use ($query) {
            if (isset($order['sql']) && $order['sql'] instanceof Expression) {
                return $order['sql']->get_value($query->get_grammar());
            }
            return $order['sql'] ?? $this->wrap($order['column']) . ' ' . $order['direction'];
        }, $orders);
    }
    /**
     * Compile the random statement into SQL.
     *
     * @param  string|int  $seed
     */
    public function compile_random($seed): string
    {
        return 'RANDOM()';
    }
    /**
     * Compile the "limit" portions of the query.
     *
     * @param  int  $limit
     */
    protected function compile_limit(Builder $query, $limit): string
    {
        return 'limit ' . (int) $limit;
    }
    /**
     * Compile a group limit clause.
     */
    protected function compile_group_limit(Builder $query): string
    {
        $select_bindings = array_merge($query->get_raw_bindings()['select'], $query->get_raw_bindings()['order']);
        $query->set_bindings($select_bindings, 'select');
        $query->set_bindings([], 'order');
        $limit = (int) $query->group_limit['value'];
        $offset = $query->offset;
        if (isset($offset)) {
            $offset = (int) $offset;
            $limit += $offset;
            $query->offset = null;
        }
        $components = $this->compile_components($query);
        $components['columns'] .= $this->compile_row_number($query->group_limit['column'], $components['orders'] ?? '');
        unset($components['orders']);
        $table = $this->wrap('laravel_table');
        $row = $this->wrap('laravel_row');
        $sql = $this->concatenate($components);
        $sql = 'select * from (' . $sql . ') as ' . $table . ' where ' . $row . ' <= ' . $limit;
        if (isset($offset)) {
            $sql .= ' and ' . $row . ' > ' . $offset;
        }
        return $sql . ' order by ' . $row;
    }
    /**
     * Compile a row number clause.
     *
     * @param  string  $partition
     */
    protected function compile_row_number($partition, string $orders): string
    {
        $over = trim('partition by ' . $this->wrap($partition) . ' ' . $orders);
        return ', row_number() over (' . $over . ') as ' . $this->wrap('laravel_row');
    }
    /**
     * Compile the "offset" portions of the query.
     *
     * @param  int  $offset
     */
    protected function compile_offset(Builder $query, $offset): string
    {
        return 'offset ' . (int) $offset;
    }
    /**
     * Compile the "union" queries attached to the main query.
     */
    protected function compile_unions(Builder $query): string
    {
        $sql = '';
        foreach ($query->unions as $union) {
            $sql .= $this->compile_union($union);
        }
        if (!empty($query->union_orders)) {
            $sql .= ' ' . $this->compile_orders($query, $query->union_orders);
        }
        if (isset($query->union_limit)) {
            $sql .= ' ' . $this->compile_limit($query, $query->union_limit);
        }
        if (isset($query->union_offset)) {
            $sql .= ' ' . $this->compile_offset($query, $query->union_offset);
        }
        return ltrim($sql);
    }
    /**
     * Compile a single union statement.
     */
    protected function compile_union(array $union): string
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';
        return $conjunction . $this->wrap_union($union['query']->to_sql());
    }
    /**
     * Wrap a union subquery in parentheses.
     */
    protected function wrap_union(string $sql): string
    {
        return '(' . $sql . ')';
    }
    /**
     * Compile a union aggregate query into SQL.
     */
    protected function compile_union_aggregate(Builder $query): string
    {
        $sql = $this->compile_aggregate($query, $query->aggregate);
        $query->aggregate = null;
        return $sql . ' from (' . $this->compile_select($query) . ') as ' . $this->wrap_table('temp_table');
    }
    /**
     * Compile an exists statement into SQL.
     */
    public function compile_exists(Builder $query): string
    {
        $select = $this->compile_select($query);
        return "select exists({$select}) as {$this->wrap('exists')}";
    }
    /**
     * Compile an insert statement into SQL.
     */
    public function compile_insert(Builder $query, array $values): string
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrap_table($query->from);
        if (empty($values)) {
            return "insert into {$table} default values";
        }
        if (!is_array(array_first($values))) {
            $values = [$values];
        }
        $columns = $this->columnize(array_keys(array_first($values)));
        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same number of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = (new Collection($values))->map(fn(array $record): string => '(' . $this->parameterize($record) . ')')->implode(', ');
        return "insert into {$table} ({$columns}) values {$parameters}";
    }
    /**
     * Compile an insert ignore statement into SQL.
     *
     *
     * @throws \RuntimeException
     */
    public function compile_insert_or_ignore(Builder $query, array $values): never
    {
        throw new RuntimeException('This database engine does not support inserting while ignoring errors.');
    }
    /**
     * Compile an insert and get ID statement into SQL.
     *
     * @param  string|null  $sequence
     */
    public function compile_insert_get_id(Builder $query, array $values, $sequence): string
    {
        return $this->compile_insert($query, $values);
    }
    /**
     * Compile an insert statement using a subquery into SQL.
     */
    public function compile_insert_using(Builder $query, array $columns, string $sql): string
    {
        $table = $this->wrap_table($query->from);
        if (empty($columns) || $columns === ['*']) {
            return "insert into {$table} {$sql}";
        }
        return "insert into {$table} ({$this->columnize($columns)}) {$sql}";
    }
    /**
     * Compile an insert ignore statement using a subquery into SQL.
     *
     *
     * @throws \RuntimeException
     */
    public function compile_insert_or_ignore_using(Builder $query, array $columns, string $sql): never
    {
        throw new RuntimeException('This database engine does not support inserting while ignoring errors.');
    }
    /**
     * Compile an update statement into SQL.
     */
    public function compile_update(Builder $query, array $values): string
    {
        $table = $this->wrap_table($query->from);
        $columns = $this->compile_update_columns($query, $values);
        $where = $this->compile_wheres($query);
        return trim(isset($query->joins) ? $this->compile_update_with_joins($query, $table, $columns, $where) : $this->compile_update_without_joins($query, $table, $columns, $where));
    }
    /**
     * Compile the columns for an update statement.
     */
    protected function compile_update_columns(Builder $query, array $values): string
    {
        return (new Collection($values))->map(fn($value, $key): string => $this->wrap($key) . ' = ' . $this->parameter($value))->implode(', ');
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
        return "update {$table} set {$columns} {$where}";
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
        $joins = $this->compile_joins($query, $query->joins);
        return "update {$table} {$joins} set {$columns} {$where}";
    }
    /**
     * Compile an "upsert" statement into SQL.
     *
     *
     * @throws \RuntimeException
     */
    public function compile_upsert(Builder $query, array $values, array $unique_by, array $update): never
    {
        throw new RuntimeException('This database engine does not support upserts.');
    }
    /**
     * Prepare the bindings for an update statement.
     */
    public function prepare_bindings_for_update(array $bindings, array $values): array
    {
        $clean_bindings = Arr::except($bindings, ['select', 'join']);
        $values = Arr::flatten(array_map(fn($value) => value($value), $values));
        return array_values(array_merge($bindings['join'], $values, Arr::flatten($clean_bindings)));
    }
    /**
     * Compile a delete statement into SQL.
     */
    public function compile_delete(Builder $query): string
    {
        $table = $this->wrap_table($query->from);
        $where = $this->compile_wheres($query);
        return trim(isset($query->joins) ? $this->compile_delete_with_joins($query, $table, $where) : $this->compile_delete_without_joins($query, $table, $where));
    }
    /**
     * Compile a delete statement without joins into SQL.
     *
     * @param  string  $table
     * @param  string  $where
     */
    protected function compile_delete_without_joins(Builder $query, $table, $where): string
    {
        return "delete from {$table} {$where}";
    }
    /**
     * Compile a delete statement with joins into SQL.
     *
     * @param  string  $table
     * @param  string  $where
     */
    protected function compile_delete_with_joins(Builder $query, $table, $where): string
    {
        $alias = last(explode(' as ', $table));
        $joins = $this->compile_joins($query, $query->joins);
        return "delete {$alias} from {$table} {$joins} {$where}";
    }
    /**
     * Prepare the bindings for a delete statement.
     */
    public function prepare_bindings_for_delete(array $bindings): array
    {
        return Arr::flatten(Arr::except($bindings, 'select'));
    }
    /**
     * Compile a truncate table statement into SQL.
     */
    public function compile_truncate(Builder $query): array
    {
        return ['truncate table ' . $this->wrap_table($query->from) => []];
    }
    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     */
    protected function compile_lock(Builder $query, $value): string
    {
        return is_string($value) ? $value : '';
    }
    /**
     * Compile a query to get the number of open connections for a database.
     *
     * @return string|null
     */
    public function compile_thread_count(): null
    {
        return null;
    }
    /**
     * Determine if the grammar supports savepoints.
     */
    public function supports_savepoints(): bool
    {
        return true;
    }
    /**
     * Compile the SQL statement to define a savepoint.
     */
    public function compile_savepoint(string $name): string
    {
        return 'SAVEPOINT ' . $name;
    }
    /**
     * Compile the SQL statement to execute a savepoint rollback.
     */
    public function compile_savepoint_roll_back(string $name): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }
    /**
     * Wrap the given JSON selector for boolean values.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrap_json_boolean_selector($value)
    {
        return $this->wrap_json_selector($value);
    }
    /**
     * Wrap the given JSON boolean value.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrap_json_boolean_value($value)
    {
        return $value;
    }
    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param  array  $segments
     */
    protected function concatenate($segments): string
    {
        return implode(' ', array_filter($segments, fn($value): bool => (string) $value !== ''));
    }
    /**
     * Remove the leading boolean from a statement.
     *
     * @param  string  $value
     * @return string
     */
    protected function remove_leading_boolean($value): ?string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }
    /**
     * Substitute the given bindings into the given raw SQL query.
     *
     * @param  string  $sql
     * @param  array  $bindings
     */
    public function substitute_bindings_into_raw_sql($sql, $bindings): string
    {
        $bindings = array_map(fn($value) => $this->escape($value, is_resource($value) || gettype($value) === 'resource (closed)'), $bindings);
        $query = '';
        $is_string_literal = false;
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            $next_char = $sql[$i + 1] ?? null;
            // Single quotes can be escaped as '' according to the SQL standard while
            // MySQL uses \'. Postgres has operators like ?| that must get encoded
            // in PHP like ??|. We should skip over the escaped characters here.
            if (in_array($char . $next_char, ["\\'", "''", '??'])) {
                $query .= $char . $next_char;
                $i += 1;
            } elseif ($char === "'") {
                // Starting / leaving string literal...
                $query .= $char;
                $is_string_literal = !$is_string_literal;
            } elseif ($char === '?' && !$is_string_literal) {
                // Substitutable binding...
                $query .= array_shift($bindings) ?? '?';
            } else {
                // Normal character...
                $query .= $char;
            }
        }
        return $query;
    }
    /**
     * Get the grammar specific operators.
     *
     * @return array
     */
    public function get_operators()
    {
        return $this->operators;
    }
    /**
     * Get the grammar specific bitwise operators.
     *
     * @return array
     */
    public function get_bitwise_operators()
    {
        return $this->bitwise_operators;
    }
}