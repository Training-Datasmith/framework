<?php

declare (strict_types=1);
namespace Illuminate\Database\Query;

use Closure;
class Join_Clause extends Builder
{
    /**
     * The connection of the parent query builder.
     *
     * @var \Illuminate\Database\ConnectionInterface
     */
    protected $parent_connection;
    /**
     * The grammar of the parent query builder.
     *
     * @var \Illuminate\Database\Query\Grammars\Grammar
     */
    protected $parent_grammar;
    /**
     * The processor of the parent query builder.
     *
     * @var \Illuminate\Database\Query\Processors\Processor
     */
    protected $parent_processor;
    /**
     * The class name of the parent query builder.
     */
    protected string $parent_class;
    /**
     * Create a new join clause instance.
     *
     * @param  string  $type
     * @param  string  $table
     */
    public function __construct(
        Builder $parent_query,
        /**
         * The type of join being performed.
         */
        public $type,
        /**
         * The table the join clause is joining to.
         */
        public $table
    )
    {
        $this->parent_class = $parent_query::class;
        $this->parent_grammar = $parent_query->get_grammar();
        $this->parent_processor = $parent_query->get_processor();
        $this->parent_connection = $parent_query->get_connection();
        parent::__construct($this->parent_connection, $this->parent_grammar, $this->parent_processor);
    }
    /**
     * Add an "on" clause to the join.
     *
     * On clauses can be chained, e.g.
     *
     *  $join->on('contacts.user_id', '=', 'users.id')
     *       ->on('contacts.info_id', '=', 'info.id')
     *
     * will produce the following SQL:
     *
     * on `contacts`.`user_id` = `users`.`id` and `contacts`.`info_id` = `info`.`id`
     *
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @param  string  $boolean
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function on($first, $operator = null, $second = null, $boolean = 'and')
    {
        if ($first instanceof Closure) {
            return $this->where_nested($first, $boolean);
        }
        return $this->where_column($first, $operator, $second, $boolean);
    }
    /**
     * Add an "or on" clause to the join.
     *
     * @param  \Closure|\Illuminate\Contracts\Database\Query\Expression|string  $first
     * @param  string|null  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|null  $second
     * @return \Illuminate\Database\Query\JoinClause
     */
    public function or_on($first, $operator = null, $second = null)
    {
        return $this->on($first, $operator, $second, 'or');
    }
    /**
     * Get a new instance of the join clause builder.
     */
    public function new_query(): static
    {
        return new static($this->new_parent_query(), $this->type, $this->table);
    }
    /**
     * Create a new query instance for sub-query.
     */
    protected function for_sub_query(): \Illuminate\Database\Query\Builder
    {
        return $this->new_parent_query()->new_query();
    }
    /**
     * Create a new parent query instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function new_parent_query()
    {
        $class = $this->parent_class;
        return new $class($this->parent_connection, $this->parent_grammar, $this->parent_processor);
    }
}