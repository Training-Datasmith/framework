<?php

declare (strict_types=1);
namespace Illuminate\Database\Query;

use Backed_Enum;
use Closure;
use DatePeriod;
use DateTimeInterface;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Contracts\Database\Query\Condition_Expression;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Concerns\Builds_Queries;
use Illuminate\Database\Concerns\Builds_Where_Date_Clauses;
use Illuminate\Database\Concerns\Explains_Queries;
use Illuminate\Database\Connection_Interface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Postgres_Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Lazy_Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Forwards_Calls;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Unit_Enum;
class Builder implements Builder_Contract
{
    /** @use \Illuminate\Database\Concerns\BuildsQueries<\stdClass> */
    use Builds_Where_Date_Clauses, Builds_Queries, Explains_Queries, Forwards_Calls, Macroable {
        __call as macroCall;
    }
    /**
     * The database connection instance.
     *
     * @var \Illuminate\Database\ConnectionInterface
     */
    public $connection;
    /**
     * The database query grammar instance.
     *
     * @var \Illuminate\Database\Query\Grammars\Grammar
     */
    public $grammar;
    /**
     * The database query post processor instance.
     *
     * @var \Illuminate\Database\Query\Processors\Processor
     */
    public $processor;
    /**
     * The current query value bindings.
     *
     * @var array{
     *     select: list<mixed>,
     *     from: list<mixed>,
     *     join: list<mixed>,
     *     where: list<mixed>,
     *     groupBy: list<mixed>,
     *     having: list<mixed>,
     *     order: list<mixed>,
     *     union: list<mixed>,
     *     unionOrder: list<mixed>,
     * }
     */
    public $bindings = ['select' => [], 'from' => [], 'join' => [], 'where' => [], 'groupBy' => [], 'having' => [], 'order' => [], 'union' => [], 'unionOrder' => []];
    /**
     * An aggregate function and column to be run.
     *
     * @var array{
     *     function: string,
     *     columns: array<\Illuminate\Contracts\Database\Query\Expression|string>
     * }|null
     */
    public $aggregate;
    /**
     * The columns that should be returned.
     *
     * @var array<string|\Illuminate\Contracts\Database\Query\Expression>|null
     */
    public $columns;
    /**
     * Indicates if the query returns distinct results.
     *
     * Occasionally contains the columns that should be distinct.
     *
     * @var bool|array
     */
    public $distinct = false;
    /**
     * The table which the query is targeting.
     *
     * @var \Illuminate\Database\Query\Expression|string
     */
    public $from;
    /**
     * The index hint for the query.
     *
     * @var \Illuminate\Database\Query\IndexHint|null
     */
    public $index_hint;
    /**
     * The table joins for the query.
     *
     * @var array|null
     */
    public $joins;
    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = [];
    /**
     * The groupings for the query.
     *
     * @var array|null
     */
    public $groups;
    /**
     * The having constraints for the query.
     *
     * @var array|null
     */
    public $havings;
    /**
     * The orderings for the query.
     *
     * @var array|null
     */
    public $orders;
    /**
     * The maximum number of records to return.
     *
     * @var int|null
     */
    public $limit;
    /**
     * The maximum number of records to return per group.
     *
     * @var array|null
     */
    public $group_limit;
    /**
     * The number of records to skip.
     *
     * @var int|null
     */
    public $offset;
    /**
     * The query union statements.
     *
     * @var array|null
     */
    public $unions;
    /**
     * The maximum number of union records to return.
     *
     * @var int|null
     */
    public $union_limit;
    /**
     * The number of union records to skip.
     *
     * @var int|null
     */
    public $union_offset;
    /**
     * The orderings for the union query.
     *
     * @var array|null
     */
    public $union_orders;
    /**
     * Indicates whether row locking is being used.
     *
     * @var string|bool|null
     */
    public $lock;
    /**
     * The query execution timeout in seconds.
     *
     * @var int|null
     */
    public $timeout;
    /**
     * The callbacks that should be invoked before the query is executed.
     *
     * @var array
     */
    public $before_query_callbacks = [];
    /**
     * The callbacks that should be invoked after retrieving data from the database.
     *
     * @var array
     */
    protected $after_query_callbacks = [];
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    public $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', '<=>', 'like', 'like binary', 'not like', 'ilike', '&', '|', '^', '<<', '>>', '&~', 'is', 'is not', 'rlike', 'not rlike', 'regexp', 'not regexp', '~', '~*', '!~', '!~*', 'similar to', 'not similar to', 'not ilike', '~~*', '!~~*'];
    /**
     * All of the available bitwise operators.
     *
     * @var string[]
     */
    public $bitwise_operators = ['&', '|', '^', '<<', '>>', '&~'];
    /**
     * Whether to use write pdo for the select.
     *
     * @var bool
     */
    public $use_write_pdo = false;
    /**
     * Create a new query builder instance.
     */
    public function __construct(Connection_Interface $connection, ?Grammar $grammar = null, ?Processor $processor = null)
    {
        $this->connection = $connection;
        $this->grammar = $grammar ?: $connection->get_query_grammar();
        $this->processor = $processor ?: $connection->get_post_processor();
    }
    /**
     * Set the columns to be selected.
     *
     * @param  mixed  $columns
     * @return $this
     */
    public function select($columns = ['*']): static
    {
        $this->columns = [];
        $this->bindings['select'] = [];
        $columns = is_array($columns) ? $columns : func_get_args();
        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->is_queryable($column)) {
                $this->select_sub($column, $as);
            } else {
                $this->columns[] = $column;
            }
        }
        return $this;
    }
    /**
     * Add a subselect expression to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @param  string  $as
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function select_sub($query, $as): static
    {
        [$query, $bindings] = $this->create_sub($query);
        return $this->select_raw('(' . $query . ') as ' . $this->grammar->wrap($as), $bindings);
    }
    /**
     * Add a select expression to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $expression
     * @param  string  $as
     * @return $this
     */
    public function select_expression($expression, $as): static
    {
        return $this->select_raw('(' . $this->grammar->get_value($expression) . ') as ' . $this->grammar->wrap($as));
    }
    /**
     * Add a new "raw" select expression to the query.
     *
     * @param  string  $expression
     * @return $this
     */
    public function select_raw($expression, array $bindings = []): static
    {
        $this->add_select(new Expression($expression));
        if ($bindings) {
            $this->add_binding($bindings, 'select');
        }
        return $this;
    }
    /**
     * Makes "from" fetch from a subquery.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @param  string  $as
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function from_sub($query, $as): static
    {
        [$query, $bindings] = $this->create_sub($query);
        return $this->from_raw('(' . $query . ') as ' . $this->grammar->wrap_table($as), $bindings);
    }
    /**
     * Add a raw "from" clause to the query.
     *
     * @param  string  $expression
     * @param  mixed  $bindings
     * @return $this
     */
    public function from_raw($expression, $bindings = []): static
    {
        $this->from = new Expression($expression);
        $this->add_binding($bindings, 'from');
        return $this;
    }
    /**
     * Creates a subquery and parse it.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     */
    protected function create_sub($query): array
    {
        // If the given query is a Closure, we will execute it while passing in a new
        // query instance to the Closure. This will give the developer a chance to
        // format and work with the query before we cast it to a raw SQL string.
        if ($query instanceof Closure) {
            $callback = $query;
            $callback($query = $this->for_sub_query());
        }
        return $this->parse_sub($query);
    }
    /**
     * Parse the subquery into SQL and bindings.
     *
     * @param  mixed  $query
     *
     * @throws \InvalidArgumentException
     */
    protected function parse_sub($query): array
    {
        if ($query instanceof self || $query instanceof Eloquent_Builder || $query instanceof Relation) {
            $query = $this->prepend_database_name_if_cross_database_query($query);
            return [$query->to_sql(), $query->get_bindings()];
        }
        if (is_string($query)) {
            return [$query, []];
        }
        throw new InvalidArgumentException('A subquery must be a query builder instance, a Closure, or a string.');
    }
    /**
     * Prepend the database name if the given query is on another database.
     *
     * @param  mixed  $query
     * @return mixed
     */
    protected function prepend_database_name_if_cross_database_query($query)
    {
        if ($query->get_connection()->get_database_name() !== $this->get_connection()->get_database_name()) {
            $database_name = $query->get_connection()->get_database_name();
            if (!str_starts_with((string) $query->from, (string) $database_name) && !str_contains((string) $query->from, '.')) {
                $query->from($database_name . '.' . $query->from);
            }
        }
        return $query;
    }
    /**
     * Add a new select column to the query.
     *
     * @param  mixed  $column
     * @return $this
     */
    public function add_select($column): static
    {
        $columns = is_array($column) ? $column : func_get_args();
        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->is_queryable($column)) {
                if (is_null($this->columns)) {
                    $this->select($this->from . '.*');
                }
                $this->select_sub($column, $as);
            } else {
                if (is_array($this->columns) && in_array($column, $this->columns, true)) {
                    continue;
                }
                $this->columns[] = $column;
            }
        }
        return $this;
    }
    /**
     * Add a vector-similarity selection to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \Illuminate\Support\Collection<int, float>|\Illuminate\Contracts\Support\Arrayable|array<int, float>|string  $vector
     * @param  string|null  $as
     * @return $this
     */
    public function select_vector_distance($column, $vector, $as = null): static
    {
        $this->ensure_connection_supports_vectors();
        if (is_string($vector)) {
            $vector = Str::of($vector)->to_embeddings(cache: true);
        }
        $this->add_binding(json_encode($vector instanceof Arrayable ? $vector->to_array() : $vector, flags: JSON_THROW_ON_ERROR), 'select');
        $as = $this->get_grammar()->wrap($as ?? $column . '_distance');
        return $this->add_select(new Expression("({$this->get_grammar()->wrap($column)} <=> ?) as {$as}"));
    }
    /**
     * Force the query to only return distinct results.
     *
     * @return $this
     */
    public function distinct(): static
    {
        $columns = func_get_args();
        if (count($columns) > 0) {
            $this->distinct = is_array($columns[0]) || is_bool($columns[0]) ? $columns[0] : $columns;
        } else {
            $this->distinct = true;
        }
        return $this;
    }
    /**
     * Set the table which the query is targeting.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  string|null  $as
     * @return $this
     */
    public function from($table, $as = null)
    {
        if ($this->is_queryable($table)) {
            return $this->from_sub($table, $as);
        }
        $this->from = $as ? "{$table} as {$as}" : $table;
        return $this;
    }
    /**
     * Add an index hint to suggest a query index.
     *
     * @param  string  $index
     * @return $this
     */
    public function use_index($index): static
    {
        $this->index_hint = new Index_Hint('hint', $index);
        return $this;
    }
    /**
     * Add an index hint to force a query index.
     *
     * @param  string  $index
     * @return $this
     */
    public function force_index($index): static
    {
        $this->index_hint = new Index_Hint('force', $index);
        return $this;
    }
    /**
     * Add an index hint to ignore a query index.
     *
     * @param  string  $index
     * @return $this
     */
    public function ignore_index($index): static
    {
        $this->index_hint = new Index_Hint('ignore', $index);
        return $this;
    }
    /**
     * Add a "join" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @param  string  $type
     * @param  bool  $where
     * @return $this
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false): static
    {
        $join = $this->new_join_clause($this, $type, $table);
        // If the first "column" of the join is really a Closure instance the developer
        // is trying to build a join with a complex "on" clause containing more than
        // one condition, so we'll add the join and call a Closure with the query.
        if ($first instanceof Closure) {
            $first($join);
            $this->joins[] = $join;
            $this->add_binding($join->get_bindings(), 'join');
        } else {
            $method = $where ? 'where' : 'on';
            $this->joins[] = $join->{$method}($first, $operator, $second);
            $this->add_binding($join->get_bindings(), 'join');
        }
        return $this;
    }
    /**
     * Add a "join where" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $second
     * @param  string  $type
     * @return $this
     */
    public function join_where($table, $first, $operator, $second, $type = 'inner'): static
    {
        return $this->join($table, $first, $operator, $second, $type, true);
    }
    /**
     * Add a "subquery join" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @param  string  $as
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @param  string  $type
     * @param  bool  $where
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function join_sub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false): static
    {
        [$query, $bindings] = $this->create_sub($query);
        $expression = '(' . $query . ') as ' . $this->grammar->wrap_table($as);
        $this->add_binding($bindings, 'join');
        return $this->join(new Expression($expression), $first, $operator, $second, $type, $where);
    }
    /**
     * Add a "lateral join" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @return $this
     */
    public function join_lateral($query, string $as, string $type = 'inner'): static
    {
        [$query, $bindings] = $this->create_sub($query);
        $expression = '(' . $query . ') as ' . $this->grammar->wrap_table($as);
        $this->add_binding($bindings, 'join');
        $this->joins[] = $this->new_join_lateral_clause($this, $type, new Expression($expression));
        return $this;
    }
    /**
     * Add a lateral left join to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @return $this
     */
    public function left_join_lateral($query, string $as): static
    {
        return $this->join_lateral($query, $as, 'left');
    }
    /**
     * Add a left join to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @return $this
     */
    public function left_join($table, $first, $operator = null, $second = null): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }
    /**
     * Add a "join where" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @return $this
     */
    public function left_join_where($table, $first, $operator, $second)
    {
        return $this->join_where($table, $first, $operator, $second, 'left');
    }
    /**
     * Add a subquery left join to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @param  string  $as
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @return $this
     */
    public function left_join_sub($query, $as, $first, $operator = null, $second = null)
    {
        return $this->join_sub($query, $as, $first, $operator, $second, 'left');
    }
    /**
     * Add a right join to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  \Closure|string  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @return $this
     */
    public function right_join($table, $first, $operator = null, $second = null): static
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }
    /**
     * Add a "right join where" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $second
     * @return $this
     */
    public function right_join_where($table, $first, $operator, $second)
    {
        return $this->join_where($table, $first, $operator, $second, 'right');
    }
    /**
     * Add a subquery right join to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @param  string  $as
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @return $this
     */
    public function right_join_sub($query, $as, $first, $operator = null, $second = null)
    {
        return $this->join_sub($query, $as, $first, $operator, $second, 'right');
    }
    /**
     * Add a "cross join" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string|null  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @return $this
     */
    public function cross_join($table, $first = null, $operator = null, $second = null): static
    {
        if ($first) {
            return $this->join($table, $first, $operator, $second, 'cross');
        }
        $this->joins[] = $this->new_join_clause($this, 'cross', $table);
        return $this;
    }
    /**
     * Add a subquery cross join to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @param  string  $as
     * @return $this
     */
    public function cross_join_sub($query, $as): static
    {
        [$query, $bindings] = $this->create_sub($query);
        $expression = '(' . $query . ') as ' . $this->grammar->wrap_table($as);
        $this->add_binding($bindings, 'join');
        $this->joins[] = $this->new_join_clause($this, 'cross', new Expression($expression));
        return $this;
    }
    /**
     * Get a new "join" clause.
     *
     * @param  string  $type
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     */
    protected function new_join_clause(self $parent_query, $type, $table): \Illuminate\Database\Query\Join_Clause
    {
        return new Join_Clause($parent_query, $type, $table);
    }
    /**
     * Get a new "join lateral" clause.
     *
     * @param  string  $type
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     */
    protected function new_join_lateral_clause(self $parent_query, $type, $table): \Illuminate\Database\Query\Join_Lateral_Clause
    {
        return new Join_Lateral_Clause($parent_query, $type, $table);
    }
    /**
     * Merge an array of "where" clauses and bindings.
     *
     * @param  array  $wheres
     * @param  array  $bindings
     * @return $this
     */
    public function merge_wheres($wheres, $bindings): static
    {
        $this->wheres = array_merge($this->wheres, (array) $wheres);
        $this->bindings['where'] = array_values(array_merge($this->bindings['where'], (array) $bindings));
        return $this;
    }
    /**
     * Add a basic "where" clause to the query.
     *
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column instanceof Condition_Expression) {
            $type = 'Expression';
            $this->wheres[] = compact('type', 'column', 'boolean');
            return $this;
        }
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->add_array_of_wheres($column, $boolean);
        }
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        // If the column is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parentheses.
        // We will add that Closure to the query and return back out immediately.
        if ($column instanceof Closure && is_null($operator)) {
            return $this->where_nested($column, $boolean);
        }
        // If the column is a Closure instance and there is an operator value, we will
        // assume the developer wants to run a subquery and then compare the result
        // of that subquery with the given value that was provided to the method.
        if ($this->is_queryable($column) && !is_null($operator)) {
            [$sub, $bindings] = $this->create_sub($column);
            return $this->add_binding($bindings, 'where')->where(new Expression('(' . $sub . ')'), $operator, $value, $boolean);
        }
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($this->is_queryable($value)) {
            return $this->where_sub($column, $operator, $value, $boolean);
        }
        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->where_null($column, $boolean, !in_array($operator, ['=', '<=>'], true));
        }
        $type = 'Basic';
        $column_string = $column instanceof Expression_Contract ? $this->grammar->get_value($column) : $column;
        // If the column is making a JSON reference we'll check to see if the value
        // is a boolean. If it is, we'll add the raw boolean string as an actual
        // value to the query to ensure this is properly handled by the query.
        if (str_contains($column_string, '->') && is_bool($value)) {
            $value = new Expression($value ? 'true' : 'false');
            if (is_string($column)) {
                $type = 'JsonBoolean';
            }
        }
        if ($this->is_bitwise_operator($operator)) {
            $type = 'Bitwise';
        }
        if ($operator === '<=>') {
            $type = 'NullSafeEquals';
        }
        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');
        if (!$value instanceof Expression_Contract) {
            $this->add_binding($this->flatten_value($value), 'where');
        }
        return $this;
    }
    /**
     * Add an array of "where" clauses to the query.
     *
     * @param  array  $column
     * @param  string  $boolean
     * @param  string  $method
     * @return $this
     */
    protected function add_array_of_wheres($column, $boolean, $method = 'where')
    {
        return $this->where_nested(function ($query) use ($column, $method, $boolean): void {
            foreach ($column as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $query->{$method}(...array_values($value), boolean: $boolean);
                } else {
                    $query->{$method}($key, '=', $value, $boolean);
                }
            }
        }, $boolean);
    }
    /**
     * Prepare the value and operator for a where clause.
     *
     * @param  string  $value
     * @param  string  $operator
     * @param  bool  $useDefault
     *
     * @throws \InvalidArgumentException
     */
    public function prepare_value_and_operator($value, $operator, $use_default = false): array
    {
        if ($use_default) {
            return [$operator, '='];
        }
        if ($this->invalid_operator_and_value($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }
        return [$value, $operator];
    }
    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param  string  $operator
     * @param  mixed  $value
     */
    protected function invalid_operator_and_value($operator, $value): bool
    {
        return is_null($value) && in_array($operator, $this->operators) && !in_array($operator, ['=', '<=>', '<>', '!=']);
    }
    /**
     * Determine if the given operator is supported.
     *
     * @param  string  $operator
     */
    protected function invalid_operator($operator): bool
    {
        return !is_string($operator) || !in_array(strtolower($operator), $this->operators, true) && !in_array(strtolower($operator), $this->grammar->get_operators(), true);
    }
    /**
     * Determine if the operator is a bitwise operator.
     *
     * @param  string  $operator
     */
    protected function is_bitwise_operator($operator): bool
    {
        return in_array(strtolower($operator), $this->bitwise_operators, true) || in_array(strtolower($operator), $this->grammar->get_bitwise_operators(), true);
    }
    /**
     * Add an "or where" clause to the query.
     *
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where($column, $operator = null, $value = null)
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->where($column, $operator, $value, 'or');
    }
    /**
     * Add a basic "where not" clause to the query.
     *
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function where_not($column, $operator = null, $value = null, string $boolean = 'and')
    {
        if (is_array($column)) {
            return $this->where_nested(function ($query) use ($column, $operator, $value, $boolean): void {
                $query->where($column, $operator, $value, $boolean);
            }, $boolean . ' not');
        }
        return $this->where($column, $operator, $value, $boolean . ' not');
    }
    /**
     * Add an "or where not" clause to the query.
     *
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_not($column, $operator = null, $value = null)
    {
        return $this->where_not($column, $operator, $value, 'or');
    }
    /**
     * Add a "where" clause comparing two columns to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|array  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @param  string|null  $boolean
     * @return $this
     */
    public function where_column($first, $operator = null, $second = null, $boolean = 'and')
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($first)) {
            return $this->add_array_of_wheres($first, $boolean, 'whereColumn');
        }
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$second, $operator] = [$operator, '='];
        }
        // Finally, we will add this where clause into this array of clauses that we
        // are building for the query. All of them will be compiled via a grammar
        // once the query is about to be executed and run against the database.
        $type = 'Column';
        $this->wheres[] = compact('type', 'first', 'operator', 'second', 'boolean');
        return $this;
    }
    /**
     * Add an "or where" clause comparing two columns to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|array  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @return $this
     */
    public function or_where_column($first, $operator = null, $second = null)
    {
        return $this->where_column($first, $operator, $second, 'or');
    }
    /**
     * Add a vector similarity clause to the query, filtering by minimum similarity and ordering by similarity.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \Illuminate\Support\Collection<int, float>|\Illuminate\Contracts\Support\Arrayable|array<int, float>|string  $vector
     * @param  float  $minSimilarity  A value between 0.0 and 1.0, where 1.0 is identical.
     * @param  bool  $order
     * @return $this
     */
    public function where_vector_similar_to($column, $vector, $min_similarity = 0.6, $order = true): static
    {
        if (is_string($vector)) {
            $vector = Str::of($vector)->to_embeddings(cache: true);
        }
        $this->where_vector_distance_less_than($column, $vector, 1 - $min_similarity);
        if ($order) {
            $this->order_by_vector_distance($column, $vector);
        }
        return $this;
    }
    /**
     * Add a vector distance "where" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \Illuminate\Support\Collection<int, float>|\Illuminate\Contracts\Support\Arrayable|array<int, float>|string  $vector
     * @param  float  $maxDistance
     * @param  string  $boolean
     * @return $this
     */
    public function where_vector_distance_less_than($column, $vector, $max_distance, $boolean = 'and'): static
    {
        $this->ensure_connection_supports_vectors();
        if (is_string($vector)) {
            $vector = Str::of($vector)->to_embeddings(cache: true);
        }
        return $this->where_raw("({$this->get_grammar()->wrap($column)} <=> ?) <= ?", [json_encode($vector instanceof Arrayable ? $vector->to_array() : $vector, flags: JSON_THROW_ON_ERROR), $max_distance], $boolean);
    }
    /**
     * Add a vector distance "or where" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \Illuminate\Support\Collection<int, float>|\Illuminate\Contracts\Support\Arrayable|array<int, float>|string  $vector
     * @param  float  $maxDistance
     * @return $this
     */
    public function or_where_vector_distance_less_than($column, $vector, $max_distance)
    {
        return $this->where_vector_distance_less_than($column, $vector, $max_distance, 'or');
    }
    /**
     * Add a raw "where" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $sql
     * @param  mixed  $bindings
     * @param  string  $boolean
     * @return $this
     */
    public function where_raw($sql, $bindings = [], $boolean = 'and'): static
    {
        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];
        $this->add_binding((array) $bindings, 'where');
        return $this;
    }
    /**
     * Add a raw "or where" clause to the query.
     *
     * @param  string  $sql
     * @param  mixed  $bindings
     * @return $this
     */
    public function or_where_raw($sql, $bindings = []): static
    {
        return $this->where_raw($sql, $bindings, 'or');
    }
    /**
     * Add a "where like" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $value
     * @param  bool  $caseSensitive
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_like($column, $value, $case_sensitive = false, $boolean = 'and', $not = false): static
    {
        $type = 'Like';
        $this->wheres[] = compact('type', 'column', 'value', 'caseSensitive', 'boolean', 'not');
        if (method_exists($this->grammar, 'prepareWhereLikeBinding')) {
            $value = $this->grammar->prepare_where_like_binding($value, $case_sensitive);
        }
        $this->add_binding($value);
        return $this;
    }
    /**
     * Add an "or where like" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $value
     * @param  bool  $caseSensitive
     * @return $this
     */
    public function or_where_like($column, $value, $case_sensitive = false): static
    {
        return $this->where_like($column, $value, $case_sensitive, 'or', false);
    }
    /**
     * Add a "where not like" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $value
     * @param  bool  $caseSensitive
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_like($column, $value, $case_sensitive = false, $boolean = 'and'): static
    {
        return $this->where_like($column, $value, $case_sensitive, $boolean, true);
    }
    /**
     * Add an "or where not like" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $value
     * @param  bool  $caseSensitive
     * @return $this
     */
    public function or_where_not_like($column, $value, $case_sensitive = false)
    {
        return $this->where_not_like($column, $value, $case_sensitive, 'or');
    }
    /**
     * Add a "where null safe equals" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_null_safe_equals($column, $value, $boolean = 'and'): static
    {
        $type = 'NullSafeEquals';
        $this->wheres[] = compact('type', 'column', 'value', 'boolean');
        if (!$value instanceof Expression_Contract) {
            $this->add_binding($this->flatten_value($value), 'where');
        }
        return $this;
    }
    /**
     * Add an "or where null safe equals" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_null_safe_equals($column, $value): static
    {
        return $this->where_null_safe_equals($column, $value, 'or');
    }
    /**
     * Add a "where in" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_in($column, $values, $boolean = 'and', $not = false): static
    {
        $type = $not ? 'NotIn' : 'In';
        // If the value is a query builder instance we will assume the developer wants to
        // look for any values that exist within this given query. So, we will add the
        // query accordingly so that this query is properly executed when it is run.
        if ($this->is_queryable($values)) {
            [$query, $bindings] = $this->create_sub($values);
            $values = [new Expression($query)];
            $this->add_binding($bindings, 'where');
        }
        // Next, if the value is Arrayable we need to cast it to its raw array form so we
        // have the underlying array value instead of an Arrayable object which is not
        // able to be added as a binding, etc. We will then add to the wheres array.
        if ($values instanceof Arrayable) {
            $values = $values->to_array();
        }
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        if (count($values) !== count(Arr::flatten($values, 1))) {
            throw new InvalidArgumentException('Nested arrays may not be passed to whereIn method.');
        }
        // Finally, we'll add a binding for each value unless that value is an expression
        // in which case we will just skip over it since it will be the query as a raw
        // string and not as a parameterized place-holder to be replaced by the PDO.
        $this->add_binding($this->clean_bindings($values), 'where');
        return $this;
    }
    /**
     * Add an "or where in" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  mixed  $values
     * @return $this
     */
    public function or_where_in($column, $values): static
    {
        return $this->where_in($column, $values, 'or');
    }
    /**
     * Add a "where not in" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_in($column, $values, $boolean = 'and'): static
    {
        return $this->where_in($column, $values, $boolean, true);
    }
    /**
     * Add an "or where not in" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  mixed  $values
     * @return $this
     */
    public function or_where_not_in($column, $values)
    {
        return $this->where_not_in($column, $values, 'or');
    }
    /**
     * Add a "where in raw" clause for integer values to the query.
     *
     * @param  string  $column
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_integer_in_raw($column, $values, $boolean = 'and', $not = false): static
    {
        $type = $not ? 'NotInRaw' : 'InRaw';
        if ($values instanceof Arrayable) {
            $values = $values->to_array();
        }
        $values = Arr::flatten($values);
        foreach ($values as &$value) {
            $value = (int) ($value instanceof Backed_Enum ? $value->value : $value);
        }
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        return $this;
    }
    /**
     * Add an "or where in raw" clause for integer values to the query.
     *
     * @param  string  $column
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $values
     * @return $this
     */
    public function or_where_integer_in_raw($column, $values): static
    {
        return $this->where_integer_in_raw($column, $values, 'or');
    }
    /**
     * Add a "where not in raw" clause for integer values to the query.
     *
     * @param  string  $column
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function where_integer_not_in_raw($column, $values, $boolean = 'and'): static
    {
        return $this->where_integer_in_raw($column, $values, $boolean, true);
    }
    /**
     * Add an "or where not in raw" clause for integer values to the query.
     *
     * @param  string  $column
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $values
     * @return $this
     */
    public function or_where_integer_not_in_raw($column, $values)
    {
        return $this->where_integer_not_in_raw($column, $values, 'or');
    }
    /**
     * Add a "where null" clause to the query.
     *
     * @param  string|array|\Illuminate\Contracts\Database\Query\Expression  $columns
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_null($columns, $boolean = 'and', $not = false): static
    {
        $type = $not ? 'NotNull' : 'Null';
        foreach (Arr::wrap($columns) as $column) {
            $this->wheres[] = compact('type', 'column', 'boolean');
        }
        return $this;
    }
    /**
     * Add an "or where null" clause to the query.
     *
     * @param  string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return $this
     */
    public function or_where_null($column): static
    {
        return $this->where_null($column, 'or');
    }
    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string|array|\Illuminate\Contracts\Database\Query\Expression  $columns
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_null($columns, $boolean = 'and'): static
    {
        return $this->where_null($columns, $boolean, true);
    }
    /**
     * Add a "where between" statement to the query.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_between($column, iterable $values, $boolean = 'and', $not = false)
    {
        $type = 'between';
        if ($this->is_queryable($column)) {
            [$sub, $bindings] = $this->create_sub($column);
            return $this->add_binding($bindings, 'where')->where_between(new Expression('(' . $sub . ')'), $values, $boolean, $not);
        }
        if ($values instanceof DatePeriod) {
            $values = $this->resolve_date_period_bounds($values);
        }
        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');
        $this->add_binding(array_slice($this->clean_bindings(Arr::flatten($values)), 0, 2), 'where');
        return $this;
    }
    /**
     * Add a "where between" statement using columns to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_between_columns($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'betweenColumns';
        if ($this->is_queryable($column)) {
            [$sub, $bindings] = $this->create_sub($column);
            return $this->add_binding($bindings, 'where')->where_between_columns(new Expression('(' . $sub . ')'), $values, $boolean, $not);
        }
        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');
        return $this;
    }
    /**
     * Add an "or where between" statement to the query.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function or_where_between($column, iterable $values)
    {
        return $this->where_between($column, $values, 'or');
    }
    /**
     * Add an "or where between" statement using columns to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function or_where_between_columns($column, array $values)
    {
        return $this->where_between_columns($column, $values, 'or');
    }
    /**
     * Add a "where not between" statement to the query.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_between($column, iterable $values, $boolean = 'and')
    {
        return $this->where_between($column, $values, $boolean, true);
    }
    /**
     * Add a "where not between" statement using columns to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_between_columns($column, array $values, $boolean = 'and')
    {
        return $this->where_between_columns($column, $values, $boolean, true);
    }
    /**
     * Add an "or where not between" statement to the query.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function or_where_not_between($column, iterable $values)
    {
        return $this->where_not_between($column, $values, 'or');
    }
    /**
     * Add an "or where not between" statement using columns to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function or_where_not_between_columns($column, array $values)
    {
        return $this->where_not_between_columns($column, $values, 'or');
    }
    /**
     * Add a "where between columns" statement using a value to the query.
     *
     * @param  mixed  $value
     * @param  array{\Illuminate\Contracts\Database\Query\Expression|string, \Illuminate\Contracts\Database\Query\Expression|string}  $columns
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_value_between($value, array $columns, $boolean = 'and', $not = false): static
    {
        $type = 'valueBetween';
        $this->wheres[] = compact('type', 'value', 'columns', 'boolean', 'not');
        $this->add_binding($value, 'where');
        return $this;
    }
    /**
     * Add an "or where between columns" statement using a value to the query.
     *
     * @param  mixed  $value
     * @param  array{\Illuminate\Contracts\Database\Query\Expression|string, \Illuminate\Contracts\Database\Query\Expression|string}  $columns
     * @return $this
     */
    public function or_where_value_between($value, array $columns): static
    {
        return $this->where_value_between($value, $columns, 'or');
    }
    /**
     * Add a "where not between columns" statement using a value to the query.
     *
     * @param  mixed  $value
     * @param  array{\Illuminate\Contracts\Database\Query\Expression|string, \Illuminate\Contracts\Database\Query\Expression|string}  $columns
     * @param  string  $boolean
     * @return $this
     */
    public function where_value_not_between($value, array $columns, $boolean = 'and'): static
    {
        return $this->where_value_between($value, $columns, $boolean, true);
    }
    /**
     * Add an "or where not between columns" statement using a value to the query.
     *
     * @param  mixed  $value
     * @param  array{\Illuminate\Contracts\Database\Query\Expression|string, \Illuminate\Contracts\Database\Query\Expression|string}  $columns
     * @return $this
     */
    public function or_where_value_not_between($value, array $columns)
    {
        return $this->where_value_not_between($value, $columns, 'or');
    }
    /**
     * Add an "or where not null" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function or_where_not_null($column)
    {
        return $this->where_not_null($column, 'or');
    }
    /**
     * Add a "where date" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|null  $operator
     * @param  \DateTimeInterface|string|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_date($column, $operator, $value = null, $boolean = 'and'): static
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        $value = $this->flatten_value($value);
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        return $this->add_date_based_where('Date', $column, $operator, $value, $boolean);
    }
    /**
     * Add an "or where date" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|null  $operator
     * @param  \DateTimeInterface|string|null  $value
     * @return $this
     */
    public function or_where_date($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->where_date($column, $operator, $value, 'or');
    }
    /**
     * Add a "where time" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|null  $operator
     * @param  \DateTimeInterface|string|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_time($column, $operator, $value = null, $boolean = 'and'): static
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        $value = $this->flatten_value($value);
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('H:i:s');
        }
        return $this->add_date_based_where('Time', $column, $operator, $value, $boolean);
    }
    /**
     * Add an "or where time" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|null  $operator
     * @param  \DateTimeInterface|string|null  $value
     * @return $this
     */
    public function or_where_time($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->where_time($column, $operator, $value, 'or');
    }
    /**
     * Add a "where day" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|int|null  $operator
     * @param  \DateTimeInterface|string|int|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_day($column, $operator, $value = null, $boolean = 'and'): static
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        $value = $this->flatten_value($value);
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('d');
        }
        if (!$value instanceof Expression_Contract) {
            $value = sprintf('%02d', $value);
        }
        return $this->add_date_based_where('Day', $column, $operator, $value, $boolean);
    }
    /**
     * Add an "or where day" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|int|null  $operator
     * @param  \DateTimeInterface|string|int|null  $value
     * @return $this
     */
    public function or_where_day($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->where_day($column, $operator, $value, 'or');
    }
    /**
     * Add a "where month" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|int|null  $operator
     * @param  \DateTimeInterface|string|int|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_month($column, $operator, $value = null, $boolean = 'and'): static
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        $value = $this->flatten_value($value);
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('m');
        }
        if (!$value instanceof Expression_Contract) {
            $value = sprintf('%02d', $value);
        }
        return $this->add_date_based_where('Month', $column, $operator, $value, $boolean);
    }
    /**
     * Add an "or where month" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|int|null  $operator
     * @param  \DateTimeInterface|string|int|null  $value
     * @return $this
     */
    public function or_where_month($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->where_month($column, $operator, $value, 'or');
    }
    /**
     * Add a "where year" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|int|null  $operator
     * @param  \DateTimeInterface|string|int|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_year($column, $operator, $value = null, $boolean = 'and'): static
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        $value = $this->flatten_value($value);
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y');
        }
        return $this->add_date_based_where('Year', $column, $operator, $value, $boolean);
    }
    /**
     * Add an "or where year" statement to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \DateTimeInterface|string|int|null  $operator
     * @param  \DateTimeInterface|string|int|null  $value
     * @return $this
     */
    public function or_where_year($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->where_year($column, $operator, $value, 'or');
    }
    /**
     * Add a date based (year, month, day, time) statement to the query.
     *
     * @param  string  $type
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    protected function add_date_based_where($type, $column, $operator, $value, $boolean = 'and'): static
    {
        $this->wheres[] = compact('column', 'type', 'boolean', 'operator', 'value');
        if (!$value instanceof Expression_Contract) {
            $this->add_binding($value, 'where');
        }
        return $this;
    }
    /**
     * Add a nested "where" statement to the query.
     *
     * @param  string  $boolean
     * @return $this
     */
    public function where_nested(Closure $callback, $boolean = 'and'): static
    {
        $callback($query = $this->for_nested_where());
        return $this->add_nested_where_query($query, $boolean);
    }
    /**
     * Create a new query instance for nested where condition.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function for_nested_where()
    {
        return $this->new_query()->from($this->from);
    }
    /**
     * Add another query builder as a nested where to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $boolean
     * @return $this
     */
    public function add_nested_where_query($query, $boolean = 'and'): static
    {
        if (count($query->wheres)) {
            $type = 'Nested';
            $this->wheres[] = compact('type', 'query', 'boolean');
            $this->add_binding($query->get_raw_bindings()['where'], 'where');
        }
        return $this;
    }
    /**
     * Add a full sub-select to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $operator
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>  $callback
     * @param  string  $boolean
     * @return $this
     */
    protected function where_sub($column, $operator, $callback, $boolean): static
    {
        $type = 'Sub';
        if ($callback instanceof Closure) {
            // Once we have the query instance we can simply execute it so it can add all
            // of the sub-select's conditions to itself, and then we can cache it off
            // in the array of where clauses for the "main" parent query instance.
            $callback($query = $this->for_sub_query());
        } else {
            $query = $callback instanceof Eloquent_Builder ? $callback->to_base() : $callback;
        }
        $this->wheres[] = compact('type', 'column', 'operator', 'query', 'boolean');
        $this->add_binding($query->get_bindings(), 'where');
        return $this;
    }
    /**
     * Add an "exists" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>  $callback
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_exists($callback, $boolean = 'and', $not = false): static
    {
        if ($callback instanceof Closure) {
            $query = $this->for_sub_query();
            // Similar to the sub-select clause, we will create a new query instance so
            // the developer may cleanly specify the entire exists query and we will
            // compile the whole thing in the grammar and insert it into the SQL.
            $callback($query);
        } else {
            $query = $callback instanceof Eloquent_Builder ? $callback->to_base() : $callback;
        }
        return $this->add_where_exists_query($query, $boolean, $not);
    }
    /**
     * Add an "or where exists" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>  $callback
     * @param  bool  $not
     * @return $this
     */
    public function or_where_exists($callback, $not = false)
    {
        return $this->where_exists($callback, 'or', $not);
    }
    /**
     * Add a "where not exists" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>  $callback
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_exists($callback, $boolean = 'and')
    {
        return $this->where_exists($callback, $boolean, true);
    }
    /**
     * Add an "or where not exists" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>  $callback
     * @return $this
     */
    public function or_where_not_exists($callback)
    {
        return $this->or_where_exists($callback, true);
    }
    /**
     * Add an "exists" clause to the query.
     *
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function add_where_exists_query(self $query, $boolean = 'and', $not = false): static
    {
        $type = $not ? 'NotExists' : 'Exists';
        $this->wheres[] = compact('type', 'query', 'boolean');
        $this->add_binding($query->get_bindings(), 'where');
        return $this;
    }
    /**
     * Adds a where condition using row values.
     *
     * @param  array  $columns
     * @param  string  $operator
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function where_row_values($columns, $operator, $values, $boolean = 'and'): static
    {
        if (count($columns) !== count($values)) {
            throw new InvalidArgumentException('The number of columns must match the number of values');
        }
        $type = 'RowValues';
        $this->wheres[] = compact('type', 'columns', 'operator', 'values', 'boolean');
        $this->add_binding($this->clean_bindings($values));
        return $this;
    }
    /**
     * Adds an or where condition using row values.
     *
     * @param  array  $columns
     * @param  string  $operator
     * @param  array  $values
     * @return $this
     */
    public function or_where_row_values($columns, $operator, $values): static
    {
        return $this->where_row_values($columns, $operator, $values, 'or');
    }
    /**
     * Add a "where JSON contains" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_json_contains($column, $value, $boolean = 'and', $not = false): static
    {
        $type = 'JsonContains';
        $this->wheres[] = compact('type', 'column', 'value', 'boolean', 'not');
        if (!$value instanceof Expression_Contract) {
            $this->add_binding($this->grammar->prepare_binding_for_json_contains($value));
        }
        return $this;
    }
    /**
     * Add an "or where JSON contains" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_json_contains($column, $value): static
    {
        return $this->where_json_contains($column, $value, 'or');
    }
    /**
     * Add a "where JSON not contains" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_json_doesnt_contain($column, $value, $boolean = 'and'): static
    {
        return $this->where_json_contains($column, $value, $boolean, true);
    }
    /**
     * Add an "or where JSON not contains" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_json_doesnt_contain($column, $value)
    {
        return $this->where_json_doesnt_contain($column, $value, 'or');
    }
    /**
     * Add a "where JSON overlaps" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_json_overlaps($column, $value, $boolean = 'and', $not = false): static
    {
        $type = 'JsonOverlaps';
        $this->wheres[] = compact('type', 'column', 'value', 'boolean', 'not');
        if (!$value instanceof Expression_Contract) {
            $this->add_binding($this->grammar->prepare_binding_for_json_contains($value));
        }
        return $this;
    }
    /**
     * Add an "or where JSON overlaps" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_json_overlaps($column, $value): static
    {
        return $this->where_json_overlaps($column, $value, 'or');
    }
    /**
     * Add a "where JSON not overlap" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_json_doesnt_overlap($column, $value, $boolean = 'and'): static
    {
        return $this->where_json_overlaps($column, $value, $boolean, true);
    }
    /**
     * Add an "or where JSON not overlap" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_json_doesnt_overlap($column, $value)
    {
        return $this->where_json_doesnt_overlap($column, $value, 'or');
    }
    /**
     * Add a clause that determines if a JSON path exists to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_json_contains_key($column, $boolean = 'and', $not = false): static
    {
        $type = 'JsonContainsKey';
        $this->wheres[] = compact('type', 'column', 'boolean', 'not');
        return $this;
    }
    /**
     * Add an "or" clause that determines if a JSON path exists to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function or_where_json_contains_key($column): static
    {
        return $this->where_json_contains_key($column, 'or');
    }
    /**
     * Add a clause that determines if a JSON path does not exist to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return $this
     */
    public function where_json_doesnt_contain_key($column, $boolean = 'and'): static
    {
        return $this->where_json_contains_key($column, $boolean, true);
    }
    /**
     * Add an "or" clause that determines if a JSON path does not exist to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function or_where_json_doesnt_contain_key($column)
    {
        return $this->where_json_doesnt_contain_key($column, 'or');
    }
    /**
     * Add a "where JSON length" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_json_length($column, $operator, $value = null, $boolean = 'and'): static
    {
        $type = 'JsonLength';
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');
        if (!$value instanceof Expression_Contract) {
            $this->add_binding((int) $this->flatten_value($value));
        }
        return $this;
    }
    /**
     * Add an "or where JSON length" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_json_length($column, $operator, $value = null): static
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->where_json_length($column, $operator, $value, 'or');
    }
    /**
     * Handles dynamic "where" clauses to the query.
     *
     * @param  string  $method
     * @return $this
     */
    public function dynamic_where($method, array $parameters): static
    {
        $finder = substr($method, 5);
        $segments = preg_split('/(And|Or)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);
        // The connector variable will determine which connector will be used for the
        // query condition. We will change it as we come across new boolean values
        // in the dynamic method strings, which could contain a number of these.
        $connector = 'and';
        $index = 0;
        foreach ($segments as $segment) {
            // If the segment is not a boolean connector, we can assume it is a column's name
            // and we will add it to the query as a new constraint as a where clause, then
            // we can keep iterating through the dynamic method string's segments again.
            if ($segment !== 'And' && $segment !== 'Or') {
                $this->add_dynamic($segment, $connector, $parameters, $index);
                $index++;
            } else {
                $connector = $segment;
            }
        }
        return $this;
    }
    /**
     * Add a single dynamic "where" clause statement to the query.
     *
     * @param  string  $segment
     * @param  string  $connector
     * @param  int  $index
     * @return void
     */
    protected function add_dynamic($segment, $connector, array $parameters, $index)
    {
        // Once we have parsed out the columns and formatted the boolean operators we
        // are ready to add it to this query as a where clause just like any other
        // clause on the query. Then we'll increment the parameter index values.
        $bool = strtolower($connector);
        $this->where(Str::snake($segment), '=', $parameters[$index], $bool);
    }
    /**
     * Add a "where fulltext" clause to the query.
     *
     * @param  string|string[]  $columns
     * @param  string  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_full_text($columns, $value, array $options = [], $boolean = 'and'): static
    {
        $type = 'Fulltext';
        $columns = (array) $columns;
        $this->wheres[] = compact('type', 'columns', 'value', 'options', 'boolean');
        $this->add_binding($value);
        return $this;
    }
    /**
     * Add an "or where fulltext" clause to the query.
     *
     * @param  string|string[]  $columns
     * @param  string  $value
     * @return $this
     */
    public function or_where_full_text($columns, $value, array $options = []): static
    {
        return $this->where_full_text($columns, $value, $options, 'or');
    }
    /**
     * Add a "where" clause to the query for multiple columns with "and" conditions between them.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression[]|\Closure[]|string[]  $columns
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_all($columns, $operator = null, $value = null, $boolean = 'and'): static
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        $this->where_nested(function ($query) use ($columns, $operator, $value): void {
            foreach ($columns as $column) {
                $query->where($column, $operator, $value, 'and');
            }
        }, $boolean);
        return $this;
    }
    /**
     * Add an "or where" clause to the query for multiple columns with "and" conditions between them.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression[]|\Closure[]|string[]  $columns
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_all($columns, $operator = null, $value = null): static
    {
        return $this->where_all($columns, $operator, $value, 'or');
    }
    /**
     * Add a "where" clause to the query for multiple columns with "or" conditions between them.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression[]|\Closure[]|string[]  $columns
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_any($columns, $operator = null, $value = null, $boolean = 'and'): static
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        $this->where_nested(function ($query) use ($columns, $operator, $value): void {
            foreach ($columns as $column) {
                $query->where($column, $operator, $value, 'or');
            }
        }, $boolean);
        return $this;
    }
    /**
     * Add an "or where" clause to the query for multiple columns with "or" conditions between them.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression[]|\Closure[]|string[]  $columns
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_any($columns, $operator = null, $value = null): static
    {
        return $this->where_any($columns, $operator, $value, 'or');
    }
    /**
     * Add a "where not" clause to the query for multiple columns where none of the conditions should be true.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression[]|\Closure[]|string[]  $columns
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function where_none($columns, $operator = null, $value = null, string $boolean = 'and'): static
    {
        return $this->where_any($columns, $operator, $value, $boolean . ' not');
    }
    /**
     * Add an "or where not" clause to the query for multiple columns where none of the conditions should be true.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression[]|\Closure[]|string[]  $columns
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_none($columns, $operator = null, $value = null)
    {
        return $this->where_none($columns, $operator, $value, 'or');
    }
    /**
     * Add a "group by" clause to the query.
     *
     * @param  array|\Illuminate\Contracts\Database\Query\Expression|string  ...$groups
     * @return $this
     */
    public function group_by(...$groups): static
    {
        foreach ($groups as $group) {
            $this->groups = array_merge((array) $this->groups, Arr::wrap($group));
        }
        return $this;
    }
    /**
     * Add a raw "groupBy" clause to the query.
     *
     * @param  string  $sql
     * @return $this
     */
    public function group_by_raw($sql, array $bindings = []): static
    {
        $this->groups[] = new Expression($sql);
        $this->add_binding($bindings, 'groupBy');
        return $this;
    }
    /**
     * Add a "having" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|\Closure|string  $column
     * @param  \DateTimeInterface|string|int|float|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|\DateTimeInterface|string|int|float|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        $type = 'Basic';
        if ($column instanceof Condition_Expression) {
            $type = 'Expression';
            $this->havings[] = compact('type', 'column', 'boolean');
            return $this;
        }
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        if ($column instanceof Closure && is_null($operator)) {
            return $this->having_nested($column, $boolean);
        }
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalid_operator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        if ($this->is_bitwise_operator($operator)) {
            $type = 'Bitwise';
        }
        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');
        if (!$value instanceof Expression_Contract) {
            $this->add_binding($this->flatten_value($value), 'having');
        }
        return $this;
    }
    /**
     * Add an "or having" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|\Closure|string  $column
     * @param  \DateTimeInterface|string|int|float|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|\DateTimeInterface|string|int|float|null  $value
     * @return $this
     */
    public function or_having($column, $operator = null, $value = null)
    {
        [$value, $operator] = $this->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->having($column, $operator, $value, 'or');
    }
    /**
     * Add a nested "having" statement to the query.
     *
     * @param  string  $boolean
     * @return $this
     */
    public function having_nested(Closure $callback, $boolean = 'and'): static
    {
        $callback($query = $this->for_nested_where());
        return $this->add_nested_having_query($query, $boolean);
    }
    /**
     * Add another query builder as a nested having to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $boolean
     * @return $this
     */
    public function add_nested_having_query($query, $boolean = 'and'): static
    {
        if (count($query->havings)) {
            $type = 'Nested';
            $this->havings[] = compact('type', 'query', 'boolean');
            $this->add_binding($query->get_raw_bindings()['having'], 'having');
        }
        return $this;
    }
    /**
     * Add a "having null" clause to the query.
     *
     * @param  array|string  $columns
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function having_null($columns, $boolean = 'and', $not = false): static
    {
        $type = $not ? 'NotNull' : 'Null';
        foreach (Arr::wrap($columns) as $column) {
            $this->havings[] = compact('type', 'column', 'boolean');
        }
        return $this;
    }
    /**
     * Add an "or having null" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function or_having_null($column): static
    {
        return $this->having_null($column, 'or');
    }
    /**
     * Add a "having not null" clause to the query.
     *
     * @param  array|string  $columns
     * @param  string  $boolean
     * @return $this
     */
    public function having_not_null($columns, $boolean = 'and'): static
    {
        return $this->having_null($columns, $boolean, true);
    }
    /**
     * Add an "or having not null" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function or_having_not_null($column)
    {
        return $this->having_not_null($column, 'or');
    }
    /**
     * Add a "having between" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function having_between($column, iterable $values, $boolean = 'and', $not = false): static
    {
        $type = 'between';
        if ($values instanceof DatePeriod) {
            $values = $this->resolve_date_period_bounds($values);
        }
        $this->havings[] = compact('type', 'column', 'values', 'boolean', 'not');
        $this->add_binding(array_slice($this->clean_bindings(Arr::flatten($values)), 0, 2), 'having');
        return $this;
    }
    /**
     * Add a "having not between" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return $this
     */
    public function having_not_between($column, iterable $values, $boolean = 'and'): static
    {
        return $this->having_between($column, $values, $boolean, true);
    }
    /**
     * Add an "or having between" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function or_having_between($column, iterable $values): static
    {
        return $this->having_between($column, $values, 'or');
    }
    /**
     * Add an "or having not between" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function or_having_not_between($column, iterable $values): static
    {
        return $this->having_between($column, $values, 'or', true);
    }
    /**
     * Resolve the start and end dates from a DatePeriod.
     *
     * @return array{\DateTimeInterface, \DateTimeInterface}
     */
    protected function resolve_date_period_bounds(DatePeriod $period): array
    {
        [$start, $end] = [$period->get_start_date(), $period->get_end_date()];
        if ($end === null) {
            $end = clone $start;
            $recurrences = $period->get_recurrences();
            for ($i = 0; $i < $recurrences; $i++) {
                $end = $end->add($period->get_date_interval());
            }
        }
        return [$start, $end];
    }
    /**
     * Add a raw "having" clause to the query.
     *
     * @param  string  $sql
     * @param  string  $boolean
     * @return $this
     */
    public function having_raw($sql, array $bindings = [], $boolean = 'and'): static
    {
        $type = 'Raw';
        $this->havings[] = compact('type', 'sql', 'boolean');
        $this->add_binding($bindings, 'having');
        return $this;
    }
    /**
     * Add a raw "or having" clause to the query.
     *
     * @param  string  $sql
     * @return $this
     */
    public function or_having_raw($sql, array $bindings = []): static
    {
        return $this->having_raw($sql, $bindings, 'or');
    }
    /**
     * Add an "order by" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $direction
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function order_by($column, $direction = 'asc'): static
    {
        if ($this->is_queryable($column)) {
            [$query, $bindings] = $this->create_sub($column);
            $column = new Expression('(' . $query . ')');
            $this->add_binding($bindings, $this->unions ? 'unionOrder' : 'order');
        }
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }
        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = ['column' => $column, 'direction' => $direction];
        return $this;
    }
    /**
     * Add a descending "order by" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function order_by_desc($column): static
    {
        return $this->order_by($column, 'desc');
    }
    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function latest($column = 'created_at'): static
    {
        return $this->order_by($column, 'desc');
    }
    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function oldest($column = 'created_at'): static
    {
        return $this->order_by($column, 'asc');
    }
    /**
     * Add a vector-distance "order by" clause to the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  \Illuminate\Support\Collection<int, float>|\Illuminate\Contracts\Support\Arrayable|array<int, float>  $vector
     * @return $this
     */
    public function order_by_vector_distance($column, $vector): static
    {
        $this->ensure_connection_supports_vectors();
        if (is_string($vector)) {
            $vector = Str::of($vector)->to_embeddings(cache: true);
        }
        $this->add_binding(json_encode($vector instanceof Arrayable ? $vector->to_array() : $vector, flags: JSON_THROW_ON_ERROR), $this->unions ? 'unionOrder' : 'order');
        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = ['column' => new Expression("({$this->get_grammar()->wrap($column)} <=> ?)"), 'direction' => 'asc'];
        return $this;
    }
    /**
     * Put the query's results in random order.
     *
     * @param  string|int  $seed
     * @return $this
     */
    public function in_random_order($seed = ''): static
    {
        return $this->order_by_raw($this->grammar->compile_random($seed));
    }
    /**
     * Add a raw "order by" clause to the query.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @return $this
     */
    public function order_by_raw($sql, $bindings = []): static
    {
        $type = 'Raw';
        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = compact('type', 'sql');
        $this->add_binding($bindings, $this->unions ? 'unionOrder' : 'order');
        return $this;
    }
    /**
     * Alias to set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function skip($value): static
    {
        return $this->offset($value);
    }
    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function offset($value): static
    {
        $property = $this->unions ? 'unionOffset' : 'offset';
        $this->{$property} = max(0, (int) $value);
        return $this;
    }
    /**
     * Alias to set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function take($value): static
    {
        return $this->limit($value);
    }
    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit($value): static
    {
        $property = $this->unions ? 'unionLimit' : 'limit';
        if ($value >= 0) {
            $this->{$property} = !is_null($value) ? (int) $value : null;
        }
        return $this;
    }
    /**
     * Add a "group limit" clause to the query.
     *
     * @param  int  $value
     * @param  string  $column
     * @return $this
     */
    public function group_limit($value, $column): static
    {
        if ($value >= 0) {
            $this->group_limit = compact('value', 'column');
        }
        return $this;
    }
    /**
     * Set the limit and offset for a given page.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return $this
     */
    public function for_page($page, $per_page = 15): static
    {
        return $this->offset(($page - 1) * $per_page)->limit($per_page);
    }
    /**
     * Constrain the query to the previous "page" of results before a given ID.
     *
     * @param  int  $perPage
     * @param  int|null  $lastId
     * @param  string  $column
     * @return $this
     */
    public function for_page_before_id($per_page = 15, $last_id = 0, $column = 'id'): static
    {
        $this->orders = $this->remove_existing_orders_for($column);
        if (is_null($last_id)) {
            $this->where_not_null($column);
        } else {
            $this->where($column, '<', $last_id);
        }
        return $this->order_by($column, 'desc')->limit($per_page);
    }
    /**
     * Constrain the query to the next "page" of results after a given ID.
     *
     * @param  int  $perPage
     * @param  int|null  $lastId
     * @param  string  $column
     * @return $this
     */
    public function for_page_after_id($per_page = 15, $last_id = 0, $column = 'id'): static
    {
        $this->orders = $this->remove_existing_orders_for($column);
        if (is_null($last_id)) {
            $this->where_not_null($column);
        } else {
            $this->where($column, '>', $last_id);
        }
        return $this->order_by($column, 'asc')->limit($per_page);
    }
    /**
     * Remove all existing orders and optionally add a new order.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Contracts\Database\Query\Expression|string|null  $column
     * @param  string  $direction
     * @return $this
     */
    public function reorder($column = null, $direction = 'asc'): static
    {
        $this->orders = null;
        $this->union_orders = null;
        $this->bindings['order'] = [];
        $this->bindings['unionOrder'] = [];
        if ($column) {
            return $this->order_by($column, $direction);
        }
        return $this;
    }
    /**
     * Add descending "reorder" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Contracts\Database\Query\Expression|string|null  $column
     * @return $this
     */
    public function reorder_desc($column)
    {
        return $this->reorder($column, 'desc');
    }
    /**
     * Get an array with all orders with a given column removed.
     *
     * @param  string  $column
     * @return array
     */
    protected function remove_existing_orders_for($column)
    {
        return (new Collection($this->orders))->reject(fn($order): bool => isset($order['column']) && $order['column'] === $column)->values()->all();
    }
    /**
     * Add a "union" statement to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  bool  $all
     * @return $this
     */
    public function union($query, $all = false): static
    {
        if ($query instanceof Closure) {
            $query($query = $this->new_query());
        }
        $this->unions[] = compact('query', 'all');
        $this->add_binding($query->get_bindings(), 'union');
        return $this;
    }
    /**
     * Add a "union all" statement to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>  $query
     * @return $this
     */
    public function union_all($query): static
    {
        return $this->union($query, true);
    }
    /**
     * Lock the selected rows in the table.
     *
     * @param  string|bool  $value
     * @return $this
     */
    public function lock($value = true): static
    {
        $this->lock = $value;
        if (!is_null($this->lock)) {
            $this->use_write_pdo();
        }
        return $this;
    }
    /**
     * Lock the selected rows in the table for updating.
     *
     * @return $this
     */
    public function lock_for_update(): static
    {
        return $this->lock(true);
    }
    /**
     * Share lock the selected rows in the table.
     *
     * @return $this
     */
    public function shared_lock(): static
    {
        return $this->lock(false);
    }
    /**
     * Set a query execution timeout in seconds.
     *
     * @return $this
     * @throws InvalidArgumentException
     */
    public function timeout(?int $seconds): static
    {
        if ($seconds !== null && $seconds <= 0) {
            throw new InvalidArgumentException('Timeout must be greater than zero.');
        }
        $this->timeout = $seconds;
        return $this;
    }
    /**
     * Register a closure to be invoked before the query is executed.
     *
     * @return $this
     */
    public function before_query(callable $callback): static
    {
        $this->before_query_callbacks[] = $callback;
        return $this;
    }
    /**
     * Invoke the "before query" modification callbacks.
     */
    public function apply_before_query_callbacks(): void
    {
        foreach ($this->before_query_callbacks as $callback) {
            $callback($this);
        }
        $this->before_query_callbacks = [];
    }
    /**
     * Register a closure to be invoked after the query is executed.
     *
     * @return $this
     */
    public function after_query(Closure $callback): static
    {
        $this->after_query_callbacks[] = $callback;
        return $this;
    }
    /**
     * Invoke the "after query" modification callbacks.
     *
     * @param  mixed  $result
     * @return mixed
     */
    public function apply_after_query_callbacks($result)
    {
        foreach ($this->after_query_callbacks as $after_query_callback) {
            $result = $after_query_callback($result) ?: $result;
        }
        return $result;
    }
    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function to_sql()
    {
        $this->apply_before_query_callbacks();
        return $this->grammar->compile_select($this);
    }
    /**
     * Get the raw SQL representation of the query with embedded bindings.
     */
    public function to_raw_sql(): string
    {
        return $this->grammar->substitute_bindings_into_raw_sql($this->to_sql(), $this->connection->prepare_bindings($this->get_bindings()));
    }
    /**
     * Execute a query for a single record by ID.
     *
     * @param  int|string  $id
     * @param  string|\Illuminate\Contracts\Database\Query\Expression|array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @return \stdClass|null
     */
    public function find($id, $columns = ['*'])
    {
        return $this->where('id', '=', $id)->first($columns);
    }
    /**
     * Execute a query for a single record by ID or call a callback.
     *
     * @template TValue
     *
     * @param  mixed  $id
     * @param  (\Closure(): TValue)|string|\Illuminate\Contracts\Database\Query\Expression|array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @param  (\Closure(): TValue)|null  $callback
     * @return \stdClass|TValue
     */
    public function find_or($id, $columns = ['*'], ?Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            $callback = $columns;
            $columns = ['*'];
        }
        if (!is_null($data = $this->find($id, $columns))) {
            return $data;
        }
        return $callback();
    }
    /**
     * Get a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function value($column)
    {
        $result = (array) $this->first([$column]);
        return count($result) > 0 ? array_first($result) : null;
    }
    /**
     * Get a single expression value from the first result of a query.
     *
     * @return mixed
     */
    public function raw_value(string $expression, array $bindings = [])
    {
        $result = (array) $this->select_raw($expression, $bindings)->first();
        return count($result) > 0 ? array_first($result) : null;
    }
    /**
     * Get a single column's value from the first result of a query if it's the sole matching record.
     *
     * @param  string  $column
     * @return mixed
     *
     * @throws \Illuminate\Database\RecordsNotFoundException
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function sole_value($column)
    {
        $result = (array) $this->sole([$column]);
        return array_first($result);
    }
    /**
     * Execute the query as a "select" statement.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression|array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function get($columns = ['*'])
    {
        $items = new Collection($this->once_with_columns(Arr::wrap($columns), fn() => $this->processor->process_select($this, $this->run_select())));
        return $this->apply_after_query_callbacks(isset($this->group_limit) ? $this->without_group_limit_keys($items) : $items);
    }
    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function run_select()
    {
        return $this->connection->select($this->to_sql(), $this->get_bindings(), !$this->use_write_pdo);
    }
    /**
     * Remove the group limit keys from the results in the collection.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @return \Illuminate\Support\Collection
     */
    protected function without_group_limit_keys($items)
    {
        $keys_to_remove = ['laravel_row'];
        if (is_string($this->group_limit['column'])) {
            $column = last(explode('.', $this->group_limit['column']));
            $keys_to_remove[] = '@laravel_group := ' . $this->grammar->wrap($column);
            $keys_to_remove[] = '@laravel_group := ' . $this->grammar->wrap('pivot_' . $column);
        }
        $items->each(function ($item) use ($keys_to_remove): void {
            foreach ($keys_to_remove as $key) {
                unset($item->{$key});
            }
        });
        return $items;
    }
    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int|\Closure  $perPage
     * @param  string|\Illuminate\Contracts\Database\Query\Expression|array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @param  \Closure|int|null  $total
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($per_page = 15, $columns = ['*'], $page_name = 'page', $page = null, $total = null)
    {
        $page = $page ?: Paginator::resolve_current_page($page_name);
        $total = value($total) ?? $this->get_count_for_pagination();
        $per_page = value($per_page, $total);
        $results = $total ? $this->for_page($page, $per_page)->get($columns) : new Collection();
        return $this->paginator($results, $total, $per_page, $page, ['path' => Paginator::resolve_current_path(), 'pageName' => $page_name]);
    }
    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * This is more efficient on larger data-sets, etc.
     *
     * @param  int  $perPage
     * @param  string|\Illuminate\Contracts\Database\Query\Expression|array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simple_paginate($per_page = 15, $columns = ['*'], $page_name = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolve_current_page($page_name);
        $this->offset(($page - 1) * $per_page)->limit($per_page + 1);
        return $this->simple_paginator($this->get($columns), $per_page, $page, ['path' => Paginator::resolve_current_path(), 'pageName' => $page_name]);
    }
    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * This is more efficient on larger data-sets, etc.
     *
     * @param  int|null  $perPage
     * @param  string|\Illuminate\Contracts\Database\Query\Expression|array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @param  string  $cursorName
     * @param  \Illuminate\Pagination\Cursor|string|null  $cursor
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    public function cursor_paginate($per_page = 15, $columns = ['*'], $cursor_name = 'cursor', $cursor = null)
    {
        return $this->paginate_using_cursor($per_page, $columns, $cursor_name, $cursor);
    }
    /**
     * Ensure the proper order by required for cursor pagination.
     *
     * @param  bool  $shouldReverse
     */
    protected function ensure_order_for_cursor_pagination($should_reverse = false): \Illuminate\Support\Collection
    {
        if (empty($this->orders) && empty($this->union_orders)) {
            $this->enforce_order_by();
        }
        $reverse_direction = function (array $order) {
            if (!isset($order['direction'])) {
                return $order;
            }
            $order['direction'] = $order['direction'] === 'asc' ? 'desc' : 'asc';
            return $order;
        };
        if ($should_reverse) {
            $this->orders = (new Collection($this->orders))->map($reverse_direction)->to_array();
            $this->union_orders = (new Collection($this->union_orders))->map($reverse_direction)->to_array();
        }
        $orders = !empty($this->union_orders) ? $this->union_orders : $this->orders;
        return (new Collection($orders))->filter(fn($order): bool => Arr::has($order, 'direction'))->values();
    }
    /**
     * Get the count of the total records for the paginator.
     *
     * @param  array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @return int<0, max>
     */
    public function get_count_for_pagination(array $columns = ['*']): int
    {
        $results = $this->run_pagination_count_query($columns);
        // Once we have run the pagination count query, we will get the resulting count and
        // take into account what type of query it was. When there is a group by we will
        // just return the count of the entire results set since that will be correct.
        if (!isset($results[0])) {
            return 0;
        }
        if (is_object($results[0])) {
            return (int) $results[0]->aggregate;
        }
        return (int) array_change_key_case((array) $results[0])['aggregate'];
    }
    /**
     * Run a pagination count query.
     *
     * @param  array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @return array<mixed>
     */
    protected function run_pagination_count_query(array $columns = ['*'])
    {
        if ($this->groups || $this->havings) {
            $clone = $this->clone_for_pagination_count();
            if (is_null($clone->columns) && !empty($this->joins)) {
                $clone->select($this->from . '.*');
            }
            return $this->new_query()->from(new Expression('(' . $clone->to_sql() . ') as ' . $this->grammar->wrap('aggregate_table')))->merge_bindings($clone)->set_aggregate('count', $this->without_select_aliases($columns))->get()->all();
        }
        $without = $this->unions ? ['unionOrders', 'unionLimit', 'unionOffset'] : ['columns', 'orders', 'limit', 'offset'];
        return $this->clone_without($without)->clone_without_bindings($this->unions ? ['unionOrder'] : ['select', 'order'])->set_aggregate('count', $this->without_select_aliases($columns))->get()->all();
    }
    /**
     * Clone the existing query instance for usage in a pagination subquery.
     *
     * @return self
     */
    protected function clone_for_pagination_count()
    {
        return $this->clone_without(['orders', 'limit', 'offset'])->clone_without_bindings(['order']);
    }
    /**
     * Remove the column aliases since they will break count queries.
     *
     * @param  array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @return array<string|\Illuminate\Contracts\Database\Query\Expression>
     */
    protected function without_select_aliases(array $columns): array
    {
        return array_map(fn(\Illuminate\Contracts\Database\Query\Expression|string $column): \Illuminate\Contracts\Database\Query\Expression|string => is_string($column) && ($alias_position = stripos($column, ' as ')) !== false ? substr($column, 0, $alias_position) : $column, $columns);
    }
    /**
     * Get a lazy collection for the given query.
     *
     * @return \Illuminate\Support\LazyCollection<int, \stdClass>
     */
    public function cursor()
    {
        if (is_null($this->columns)) {
            $this->columns = ['*'];
        }
        return (new Lazy_Collection(function () {
            yield from $this->connection->cursor($this->to_sql(), $this->get_bindings(), !$this->use_write_pdo);
        }))->map(fn($item) => $this->apply_after_query_callbacks(new Collection([$item]))->first())->reject(fn($item): bool => is_null($item));
    }
    /**
     * Throw an exception if the query doesn't have an orderBy clause.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function enforce_order_by()
    {
        if (empty($this->orders) && empty($this->union_orders)) {
            throw new RuntimeException('You must specify an orderBy clause when using this function.');
        }
    }
    /**
     * Get a collection instance containing the values of a given column.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    public function pluck($column, $key = null)
    {
        // First, we will need to select the results of the query accounting for the
        // given columns / key. Once we have the results, we will be able to take
        // the results and get the exact data that was requested for the query.
        $query_result = $this->once_with_columns(is_null($key) || $key === $column ? [$column] : [$column, $key], fn() => $this->processor->process_select($this, $this->run_select()));
        if (empty($query_result)) {
            return new Collection();
        }
        // If the columns are qualified with a table or have an alias, we cannot use
        // those directly in the "pluck" operations since the results from the DB
        // are only keyed by the column itself. We'll strip the table out here.
        $column = $this->strip_table_for_pluck($column);
        $key = $this->strip_table_for_pluck($key);
        return $this->apply_after_query_callbacks(is_array($query_result[0]) ? $this->pluck_from_array_column($query_result, $column, $key) : $this->pluck_from_object_column($query_result, $column, $key));
    }
    /**
     * Strip off the table name or alias from a column identifier.
     *
     * @param  string  $column
     * @return string|null
     */
    protected function strip_table_for_pluck($column)
    {
        if (is_null($column)) {
            return $column;
        }
        $column_string = $column instanceof Expression_Contract ? $this->grammar->get_value($column) : $column;
        $separator = str_contains(strtolower($column_string), ' as ') ? ' as ' : '\.';
        return last(preg_split('~' . $separator . '~i', $column_string));
    }
    /**
     * Retrieve column values from rows represented as objects.
     *
     * @param  array  $queryResult
     * @param  string  $column
     * @param  string  $key
     */
    protected function pluck_from_object_column($query_result, $column, $key): \Illuminate\Support\Collection
    {
        $results = [];
        if (is_null($key)) {
            foreach ($query_result as $row) {
                $results[] = $row->{$column};
            }
        } else {
            foreach ($query_result as $row) {
                $results[$row->{$key}] = $row->{$column};
            }
        }
        return new Collection($results);
    }
    /**
     * Retrieve column values from rows represented as arrays.
     *
     * @param  array  $queryResult
     * @param  string  $column
     * @param  string  $key
     */
    protected function pluck_from_array_column($query_result, $column, $key): \Illuminate\Support\Collection
    {
        $results = [];
        if (is_null($key)) {
            foreach ($query_result as $row) {
                $results[] = $row[$column];
            }
        } else {
            foreach ($query_result as $row) {
                $results[$row[$key]] = $row[$column];
            }
        }
        return new Collection($results);
    }
    /**
     * Concatenate values of a given column as a string.
     *
     * @param  string  $column
     * @param  string  $glue
     */
    public function implode($column, $glue = ''): string
    {
        return $this->pluck($column)->implode($glue);
    }
    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        $this->apply_before_query_callbacks();
        $results = $this->connection->select($this->grammar->compile_exists($this), $this->get_bindings(), !$this->use_write_pdo);
        // If the results have rows, we will get the row and see if the exists column is a
        // boolean true. If there are no results for this query we will return false as
        // there are no rows for this query at all, and we can return that info here.
        if (isset($results[0])) {
            $results = (array) $results[0];
            return (bool) $results['exists'];
        }
        return false;
    }
    /**
     * Determine if no rows exist for the current query.
     */
    public function doesnt_exist(): bool
    {
        return !$this->exists();
    }
    /**
     * Execute the given callback if no rows exist for the current query.
     *
     * @return mixed
     */
    public function exists_or(Closure $callback)
    {
        return $this->exists() ? true : $callback();
    }
    /**
     * Execute the given callback if rows exist for the current query.
     *
     * @return mixed
     */
    public function doesnt_exist_or(Closure $callback)
    {
        return $this->doesnt_exist() ? true : $callback();
    }
    /**
     * Retrieve the "count" result of the query.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $columns
     * @return int<0, max>
     */
    public function count($columns = '*'): int
    {
        return (int) $this->aggregate(__FUNCTION__, Arr::wrap($columns));
    }
    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }
    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }
    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function sum($column)
    {
        $result = $this->aggregate(__FUNCTION__, [$column]);
        return $result ?: 0;
    }
    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }
    /**
     * Alias for the "avg" method.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }
    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array  $columns
     * @return mixed
     */
    public function aggregate($function, $columns = ['*'])
    {
        $results = $this->clone_without($this->unions || $this->havings ? [] : ['columns'])->clone_without_bindings($this->unions || $this->havings ? [] : ['select'])->set_aggregate($function, $columns)->get($columns);
        if (!$results->is_empty()) {
            return array_change_key_case((array) $results[0])['aggregate'];
        }
    }
    /**
     * Execute a numeric aggregate function on the database.
     *
     * @param  string  $function
     * @param  array  $columns
     */
    public function numeric_aggregate($function, $columns = ['*']): float|int
    {
        $result = $this->aggregate($function, $columns);
        // If there is no result, we can obviously just return 0 here. Next, we will check
        // if the result is an integer or float. If it is already one of these two data
        // types we can just return the result as-is, otherwise we will convert this.
        if (!$result) {
            return 0;
        }
        if (is_int($result) || is_float($result)) {
            return $result;
        }
        // If the result doesn't contain a decimal place, we will assume it is an int then
        // cast it to one. When it does we will cast it to a float since it needs to be
        // cast to the expected data type for the developers out of pure convenience.
        return !str_contains((string) $result, '.') ? (int) $result : (float) $result;
    }
    /**
     * Set the aggregate property without running the query.
     *
     * @param  string  $function
     * @param  array<\Illuminate\Contracts\Database\Query\Expression|string>  $columns
     * @return $this
     */
    protected function set_aggregate($function, $columns): static
    {
        $this->aggregate = compact('function', 'columns');
        if (empty($this->groups)) {
            $this->orders = null;
            $this->bindings['order'] = [];
        }
        return $this;
    }
    /**
     * Execute the given callback while selecting the given columns.
     *
     * After running the callback, the columns are reset to the original value.
     *
     * @template TResult
     *
     * @param  array<string|\Illuminate\Contracts\Database\Query\Expression>  $columns
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    protected function once_with_columns($columns, $callback)
    {
        $original = $this->columns;
        if (is_null($original)) {
            $this->columns = $columns;
        }
        $result = $callback();
        $this->columns = $original;
        return $result;
    }
    /**
     * Insert new records into the database.
     *
     * @return bool
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }
        if (!is_array(array_first($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }
        $this->apply_before_query_callbacks();
        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->insert($this->grammar->compile_insert($this, $values), $this->clean_bindings(Arr::flatten($values, 1)));
    }
    /**
     * Insert new records into the database while ignoring errors.
     *
     * @return int<0, max>
     */
    public function insert_or_ignore(array $values)
    {
        if (empty($values)) {
            return 0;
        }
        if (!is_array(array_first($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }
        $this->apply_before_query_callbacks();
        return $this->connection->affecting_statement($this->grammar->compile_insert_or_ignore($this, $values), $this->clean_bindings(Arr::flatten($values, 1)));
    }
    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  string|null  $sequence
     * @return int
     */
    public function insert_get_id(array $values, $sequence = null)
    {
        $this->apply_before_query_callbacks();
        $sql = $this->grammar->compile_insert_get_id($this, $values, $sequence);
        $values = $this->clean_bindings($values);
        return $this->processor->process_insert_get_id($this, $sql, $values, $sequence);
    }
    /**
     * Insert new records into the table using a subquery.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @return int
     */
    public function insert_using(array $columns, $query)
    {
        $this->apply_before_query_callbacks();
        [$sql, $bindings] = $this->create_sub($query);
        return $this->connection->affecting_statement($this->grammar->compile_insert_using($this, $columns, $sql), $this->clean_bindings($bindings));
    }
    /**
     * Insert new records into the table using a subquery while ignoring errors.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     * @return int
     */
    public function insert_or_ignore_using(array $columns, $query)
    {
        $this->apply_before_query_callbacks();
        [$sql, $bindings] = $this->create_sub($query);
        return $this->connection->affecting_statement($this->grammar->compile_insert_or_ignore_using($this, $columns, $sql), $this->clean_bindings($bindings));
    }
    /**
     * Update records in the database.
     *
     * @return int<0, max>
     */
    public function update(array $values)
    {
        $this->apply_before_query_callbacks();
        $values = (new Collection($values))->map(function ($value): array {
            if (!$value instanceof self && !$value instanceof Eloquent_Builder && !$value instanceof Relation) {
                return ['value' => $value, 'bindings' => match (true) {
                    $value instanceof Collection => $value->all(),
                    $value instanceof Unit_Enum => enum_value($value),
                    default => $value,
                }];
            }
            [$query, $bindings] = $this->parse_sub($value);
            return ['value' => new Expression("({$query})"), 'bindings' => fn() => $bindings];
        });
        $sql = $this->grammar->compile_update($this, $values->map(fn($value) => $value['value'])->all());
        return $this->connection->update($sql, $this->clean_bindings($this->grammar->prepare_bindings_for_update($this->bindings, $values->map(fn($value) => $value['bindings'])->all())));
    }
    /**
     * Update records in a PostgreSQL database using the update from syntax.
     *
     * @return int
     */
    public function update_from(array $values)
    {
        if (!method_exists($this->grammar, 'compileUpdateFrom')) {
            throw new LogicException('This database engine does not support the updateFrom method.');
        }
        $this->apply_before_query_callbacks();
        $sql = $this->grammar->compile_update_from($this, $values);
        return $this->connection->update($sql, $this->clean_bindings($this->grammar->prepare_bindings_for_update_from($this->bindings, $values)));
    }
    /**
     * Insert or update a record matching the attributes, and fill it with values.
     *
     * @return bool
     */
    public function update_or_insert(array $attributes, array|callable $values = [])
    {
        $exists = $this->where($attributes)->exists();
        if ($values instanceof Closure) {
            $values = $values($exists);
        }
        if (!$exists) {
            return $this->insert(array_merge($attributes, $values));
        }
        if (empty($values)) {
            return true;
        }
        return (bool) $this->limit(1)->update($values);
    }
    /**
     * Insert new records or update the existing ones.
     *
     * @return int
     */
    public function upsert(array $values, array|string $unique_by, ?array $update = null)
    {
        if (empty($values)) {
            return 0;
        }
        if ($update === []) {
            return (int) $this->insert($values);
        }
        if (!is_array(array_first($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }
        if (is_null($update)) {
            $update = array_keys(array_first($values));
        }
        $this->apply_before_query_callbacks();
        $bindings = $this->clean_bindings(array_merge(Arr::flatten($values, 1), (new Collection($update))->reject(fn($value, $key): bool => is_int($key))->all()));
        return $this->connection->affecting_statement($this->grammar->compile_upsert($this, $values, (array) $unique_by, $update), $bindings);
    }
    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @return int<0, max>
     *
     * @throws \InvalidArgumentException
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to increment method.');
        }
        return $this->increment_each([$column => $amount], $extra);
    }
    /**
     * Increment the given column's values by the given amounts.
     *
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     * @return int<0, max>
     *
     * @throws \InvalidArgumentException
     */
    public function increment_each(array $columns, array $extra = [])
    {
        foreach ($columns as $column => $amount) {
            if (!is_numeric($amount)) {
                throw new InvalidArgumentException("Non-numeric value passed as increment amount for column: '{$column}'.");
            }
            if (!is_string($column)) {
                throw new InvalidArgumentException('Non-associative array passed to incrementEach method.');
            }
            $columns[$column] = $this->raw("{$this->grammar->wrap($column)} + {$amount}");
        }
        return $this->update(array_merge($columns, $extra));
    }
    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @return int<0, max>
     *
     * @throws \InvalidArgumentException
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to decrement method.');
        }
        return $this->decrement_each([$column => $amount], $extra);
    }
    /**
     * Decrement the given column's values by the given amounts.
     *
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     * @return int<0, max>
     *
     * @throws \InvalidArgumentException
     */
    public function decrement_each(array $columns, array $extra = [])
    {
        foreach ($columns as $column => $amount) {
            if (!is_numeric($amount)) {
                throw new InvalidArgumentException("Non-numeric value passed as decrement amount for column: '{$column}'.");
            }
            if (!is_string($column)) {
                throw new InvalidArgumentException('Non-associative array passed to decrementEach method.');
            }
            $columns[$column] = $this->raw("{$this->grammar->wrap($column)} - {$amount}");
        }
        return $this->update(array_merge($columns, $extra));
    }
    /**
     * Delete records from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where($this->from . '.id', '=', $id);
        }
        $this->apply_before_query_callbacks();
        return $this->connection->delete($this->grammar->compile_delete($this), $this->clean_bindings($this->grammar->prepare_bindings_for_delete($this->bindings)));
    }
    /**
     * Run a "truncate" statement on the table.
     */
    public function truncate(): void
    {
        $this->apply_before_query_callbacks();
        foreach ($this->grammar->compile_truncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
    }
    /**
     * Get a new instance of the query builder.
     */
    public function new_query(): static
    {
        return new static($this->connection, $this->grammar, $this->processor);
    }
    /**
     * Create a new query instance for a sub-query.
     */
    protected function for_sub_query(): static
    {
        return $this->new_query();
    }
    /**
     * Get all of the query builder's columns in a text-only array with all expressions evaluated.
     *
     * @return list<string>
     */
    public function get_columns(): array
    {
        return !is_null($this->columns) ? array_map($this->grammar->get_value(...), $this->columns) : [];
    }
    /**
     * Create a raw database expression.
     *
     * @param  mixed  $value
     * @return \Illuminate\Contracts\Database\Query\Expression
     */
    public function raw($value)
    {
        return $this->connection->raw($value);
    }
    /**
     * Get the query builder instances that are used in the union of the query.
     */
    protected function get_union_builders(): \Illuminate\Support\Collection
    {
        return isset($this->unions) ? (new Collection($this->unions))->pluck('query') : new Collection();
    }
    /**
     * Get the "limit" value for the query or null if it's not set.
     */
    public function get_limit(): ?int
    {
        $value = $this->unions ? $this->union_limit : $this->limit;
        return !is_null($value) ? (int) $value : null;
    }
    /**
     * Get the "offset" value for the query or null if it's not set.
     */
    public function get_offset(): ?int
    {
        $value = $this->unions ? $this->union_offset : $this->offset;
        return !is_null($value) ? (int) $value : null;
    }
    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return list<mixed>
     */
    public function get_bindings(): array
    {
        return Arr::flatten($this->bindings);
    }
    /**
     * Get the raw array of bindings.
     *
     * @return array{
     *      select: list<mixed>,
     *      from: list<mixed>,
     *      join: list<mixed>,
     *      where: list<mixed>,
     *      groupBy: list<mixed>,
     *      having: list<mixed>,
     *      order: list<mixed>,
     *      union: list<mixed>,
     *      unionOrder: list<mixed>,
     * }
     */
    public function get_raw_bindings()
    {
        return $this->bindings;
    }
    /**
     * Set the bindings on the query builder.
     *
     * @param  list<mixed>  $bindings
     * @param  "select"|"from"|"join"|"where"|"groupBy"|"having"|"order"|"union"|"unionOrder"  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function set_bindings(array $bindings, $type = 'where'): static
    {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }
        $this->bindings[$type] = $bindings;
        return $this;
    }
    /**
     * Add a binding to the query.
     *
     * @param  mixed  $value
     * @param  "select"|"from"|"join"|"where"|"groupBy"|"having"|"order"|"union"|"unionOrder"  $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function add_binding($value, $type = 'where'): static
    {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }
        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_map($this->cast_binding(...), array_merge($this->bindings[$type], $value)));
        } else {
            $this->bindings[$type][] = $this->cast_binding($value);
        }
        return $this;
    }
    /**
     * Cast the given binding value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function cast_binding($value)
    {
        return enum_value($value);
    }
    /**
     * Merge an array of bindings into our bindings.
     *
     * @return $this
     */
    public function merge_bindings(self $query): static
    {
        $this->bindings = array_merge_recursive($this->bindings, $query->bindings);
        return $this;
    }
    /**
     * Remove all of the expressions from a list of bindings.
     *
     * @param  array<mixed>  $bindings
     * @return list<mixed>
     */
    public function clean_bindings(array $bindings)
    {
        return (new Collection($bindings))->reject(fn($binding): bool => $binding instanceof Expression_Contract)->map($this->cast_binding(...))->values()->all();
    }
    /**
     * Get a scalar type value from an unknown type of input.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function flatten_value($value)
    {
        return is_array($value) ? head(Arr::flatten($value)) : $value;
    }
    /**
     * Get the default key name of the table.
     */
    protected function default_key_name(): string
    {
        return 'id';
    }
    /**
     * Get the database connection instance.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function get_connection()
    {
        return $this->connection;
    }
    /**
     * Ensure the database connection supports vector queries.
     *
     * @return void
     */
    protected function ensure_connection_supports_vectors()
    {
        if (!$this->connection instanceof Postgres_Connection) {
            throw new RuntimeException('Vector distance queries are only supported by Postgres.');
        }
    }
    /**
     * Get the database query processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\Processor
     */
    public function get_processor()
    {
        return $this->processor;
    }
    /**
     * Get the query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    public function get_grammar()
    {
        return $this->grammar;
    }
    /**
     * Use the "write" PDO connection when executing the query.
     *
     * @return $this
     */
    public function use_write_pdo(): static
    {
        $this->use_write_pdo = true;
        return $this;
    }
    /**
     * Determine if the value is a query builder instance or a Closure.
     *
     * @param  mixed  $value
     */
    protected function is_queryable($value): bool
    {
        return $value instanceof self || $value instanceof Eloquent_Builder || $value instanceof Relation || $value instanceof Closure;
    }
    /**
     * Clone the query.
     */
    public function clone(): static
    {
        return clone $this;
    }
    /**
     * Clone the query without the given properties.
     *
     * @return static
     */
    public function clone_without(array $properties)
    {
        return tap($this->clone(), function ($clone) use ($properties): void {
            foreach ($properties as $property) {
                $clone->{$property} = null;
            }
        });
    }
    /**
     * Clone the query without the given bindings.
     *
     * @return static
     */
    public function clone_without_bindings(array $except)
    {
        return tap($this->clone(), function ($clone) use ($except): void {
            foreach ($except as $type) {
                $clone->bindings[$type] = [];
            }
        });
    }
    /**
     * Dump the current SQL and bindings.
     *
     * @param  mixed  ...$args
     * @return $this
     */
    public function dump(...$args): static
    {
        dump($this->to_sql(), $this->get_bindings(), ...$args);
        return $this;
    }
    /**
     * Dump the raw current SQL with embedded bindings.
     *
     * @return $this
     */
    public function dump_raw_sql(): static
    {
        dump($this->to_raw_sql());
        return $this;
    }
    /**
     * Die and dump the current SQL and bindings.
     *
     * @return never
     */
    public function dd(): void
    {
        dd($this->to_sql(), $this->get_bindings());
    }
    /**
     * Die and dump the current SQL with embedded bindings.
     *
     * @return never
     */
    public function dd_raw_sql(): void
    {
        dd($this->to_raw_sql());
    }
    /**
     * Handle dynamic method calls into the method.
     *
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        if (str_starts_with($method, 'where')) {
            return $this->dynamic_where($method, $parameters);
        }
        static::throw_bad_method_call_exception($method);
    }
}