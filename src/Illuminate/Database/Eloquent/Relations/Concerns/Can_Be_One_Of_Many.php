<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Join_Clause;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
trait Can_Be_One_Of_Many
{
    /**
     * Determines whether the relationship is one-of-many.
     *
     * @var bool
     */
    protected $is_one_of_many = false;
    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relation_name;
    /**
     * The one of many inner join subselect query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder<*>|null
     */
    protected $one_of_many_sub_query;
    /**
     * Add constraints for inner join subselect for one of many relationships.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  string|null  $column
     * @param  string|null  $aggregate
     * @return void
     */
    abstract public function add_one_of_many_sub_query_constraints(Builder $query, $column = null, $aggregate = null);
    /**
     * Get the columns the determine the relationship groups.
     *
     * @return array|string
     */
    abstract public function get_one_of_many_sub_query_select_columns();
    /**
     * Add join query constraints for one of many relationships.
     *
     * @return void
     */
    abstract public function add_one_of_many_join_sub_query_constraints(Join_Clause $join);
    /**
     * Indicate that the relation is a single result of a larger one-to-many relationship.
     *
     * @param  string|array|null  $column
     * @param  string|\Closure|null  $aggregate
     * @param  string|null  $relation
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function of_many($column = 'id', $aggregate = 'MAX', $relation = null)
    {
        $this->is_one_of_many = true;
        $this->relation_name = $relation ?: $this->get_default_one_of_many_join_alias($this->guess_relationship());
        $key_name = $this->query->get_model()->get_key_name();
        $columns = is_string($columns = $column) ? [$column => $aggregate, $key_name => $aggregate] : $column;
        if (!array_key_exists($key_name, $columns)) {
            $columns[$key_name] = 'MAX';
        }
        if ($aggregate instanceof Closure) {
            $closure = $aggregate;
        }
        foreach ($columns as $column => $aggregate) {
            if (!in_array(strtolower((string) $aggregate), ['min', 'max'])) {
                throw new InvalidArgumentException("Invalid aggregate [{$aggregate}] used within ofMany relation. Available aggregates: MIN, MAX");
            }
            $sub_query = $this->new_one_of_many_sub_query($this->get_one_of_many_sub_query_select_columns(), array_merge([$column], $previous['columns'] ?? []), $aggregate);
            if (isset($previous)) {
                $this->add_one_of_many_join_sub_query($sub_query, $previous['subQuery'], $previous['columns']);
            }
            if (isset($closure)) {
                $closure($sub_query);
            }
            if (!isset($previous)) {
                $this->one_of_many_sub_query = $sub_query;
            }
            if (array_key_last($columns) == $column) {
                $this->add_one_of_many_join_sub_query($this->query, $sub_query, array_merge([$column], $previous['columns'] ?? []));
            }
            $previous = ['subQuery' => $sub_query, 'columns' => array_merge([$column], $previous['columns'] ?? [])];
        }
        $this->add_constraints();
        $columns = $this->query->get_query()->columns;
        if (is_null($columns) || $columns === ['*']) {
            $this->select([$this->qualify_column('*')]);
        }
        return $this;
    }
    /**
     * Indicate that the relation is the latest single result of a larger one-to-many relationship.
     *
     * @param  string|array|null  $column
     * @param  string|null  $relation
     * @return $this
     */
    public function latest_of_many($column = 'id', $relation = null)
    {
        return $this->of_many(Collection::wrap($column)->map_with_keys(fn($column): array => [$column => 'MAX'])->all(), 'MAX', $relation);
    }
    /**
     * Indicate that the relation is the oldest single result of a larger one-to-many relationship.
     *
     * @param  string|array|null  $column
     * @param  string|null  $relation
     * @return $this
     */
    public function oldest_of_many($column = 'id', $relation = null)
    {
        return $this->of_many(Collection::wrap($column)->map_with_keys(fn($column): array => [$column => 'MIN'])->all(), 'MIN', $relation);
    }
    /**
     * Get the default alias for the one of many inner join clause.
     *
     * @param  string  $relation
     * @return string
     */
    protected function get_default_one_of_many_join_alias($relation)
    {
        return $relation == $this->query->get_model()->get_table() ? $relation . '_of_many' : $relation;
    }
    /**
     * Get a new query for the related model, grouping the query by the given column, often the foreign key of the relationship.
     *
     * @param  string|array  $groupBy
     * @param  array<string>|null  $columns
     * @param  string|null  $aggregate
     * @return \Illuminate\Database\Eloquent\Builder<*>
     */
    protected function new_one_of_many_sub_query($group_by, $columns = null, $aggregate = null)
    {
        $sub_query = $this->query->get_model()->new_query()->without_global_scopes($this->removed_scopes());
        foreach (Arr::wrap($group_by) as $group) {
            $sub_query->group_by($this->qualify_related_column($group));
        }
        if (!is_null($columns)) {
            foreach ($columns as $key => $column) {
                $aggregated_column = $sub_query->get_query()->grammar->wrap($sub_query->qualify_column($column));
                if ($key === 0) {
                    $aggregated_column = "{$aggregate}({$aggregated_column})";
                } else {
                    $aggregated_column = "min({$aggregated_column})";
                }
                $sub_query->select_raw($aggregated_column . ' as ' . $sub_query->get_query()->grammar->wrap($column . '_aggregate'));
            }
        }
        $this->add_one_of_many_sub_query_constraints($sub_query, column: null, aggregate: $aggregate);
        return $sub_query;
    }
    /**
     * Add the join subquery to the given query on the given column and the relationship's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $parent
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $subQuery
     * @param  array<string>  $on
     * @return void
     */
    protected function add_one_of_many_join_sub_query(Builder $parent, Builder $sub_query, $on)
    {
        $parent->before_query(function ($parent) use ($sub_query, $on): void {
            $sub_query->apply_before_query_callbacks();
            $parent->join_sub($sub_query, $this->relation_name, function ($join) use ($on): void {
                foreach ($on as $on_column) {
                    $join->on($this->qualify_sub_select_column($on_column . '_aggregate'), '=', $this->qualify_related_column($on_column));
                }
                $this->add_one_of_many_join_sub_query_constraints($join);
            });
        });
    }
    /**
     * Merge the relationship query joins to the given query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @return void
     */
    protected function merge_one_of_many_joins_to(Builder $query)
    {
        $query->get_query()->before_query_callbacks = $this->query->get_query()->before_query_callbacks;
        $query->apply_before_query_callbacks();
    }
    /**
     * Get the query builder that will contain the relationship constraints.
     *
     * @return \Illuminate\Database\Eloquent\Builder<*>
     */
    protected function get_relation_query()
    {
        return $this->is_one_of_many() ? $this->one_of_many_sub_query : $this->query;
    }
    /**
     * Get the one of many inner join subselect builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder<*>|void
     */
    public function get_one_of_many_sub_query()
    {
        return $this->one_of_many_sub_query;
    }
    /**
     * Get the qualified column name for the one-of-many relationship using the subselect join query's alias.
     *
     * @param  string  $column
     */
    public function qualify_sub_select_column($column): string
    {
        return $this->get_relation_name() . '.' . last(explode('.', $column));
    }
    /**
     * Qualify related column using the related table name if it is not already qualified.
     *
     * @param  string  $column
     * @return string
     */
    protected function qualify_related_column($column)
    {
        return $this->query->get_model()->qualify_column($column);
    }
    /**
     * Guess the "hasOne" relationship's name via backtrace.
     */
    protected function guess_relationship(): string
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'];
    }
    /**
     * Determine whether the relationship is a one-of-many relationship.
     *
     * @return bool
     */
    public function is_one_of_many()
    {
        return $this->is_one_of_many;
    }
    /**
     * Get the name of the relationship.
     *
     * @return string
     */
    public function get_relation_name()
    {
        return $this->relation_name;
    }
}