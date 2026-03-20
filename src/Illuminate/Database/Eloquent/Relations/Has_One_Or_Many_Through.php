<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model_Not_Found_Exception;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Dictionary;
use Illuminate\Database\Query\Grammars\My_Sql_Grammar;
use Illuminate\Database\Unique_Constraint_Violation_Exception;
use Illuminate\Support\Arr;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TResult
 *
 * @extends \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, TIntermediateModel, TResult>
 */
abstract class Has_One_Or_Many_Through extends Relation
{
    use Interacts_With_Dictionary;
    /**
     * Create a new has many through relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $farParent
     * @param  TIntermediateModel  $throughParent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     */
    public function __construct(
        Builder $query,
        /**
         * The far parent model instance.
         */
        protected \Illuminate\Database\Eloquent\Model $far_parent,
        /**
         * The "through" parent model instance.
         */
        protected \Illuminate\Database\Eloquent\Model $through_parent,
        /**
         * The near key on the relationship.
         */
        protected $first_key,
        /**
         * The far key on the relationship.
         */
        protected $second_key,
        /**
         * The local key on the relationship.
         */
        protected $local_key,
        /**
         * The local key on the intermediary model.
         */
        protected $second_local_key
    )
    {
        parent::__construct($query, $this->through_parent);
    }
    /**
     * Set the base constraints on the relation query.
     */
    public function add_constraints(): void
    {
        $query = $this->get_relation_query();
        $local_value = $this->far_parent[$this->local_key];
        $this->perform_join($query);
        if (static::$constraints) {
            $query->where($this->get_qualified_first_key_name(), '=', $local_value);
        }
    }
    /**
     * Set the join clause on the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>|null  $query
     * @return void
     */
    protected function perform_join(?Builder $query = null)
    {
        $query ??= $this->query;
        $far_key = $this->get_qualified_far_key_name();
        $query->join($this->through_parent->get_table(), $this->get_qualified_parent_key_name(), '=', $far_key);
        if ($this->through_parent_soft_deletes()) {
            $query->with_global_scope('SoftDeletableHasManyThrough', function ($query): void {
                $query->where_null($this->through_parent->get_qualified_deleted_at_column());
            });
        }
    }
    /**
     * Get the fully-qualified parent key name.
     *
     * @return string
     */
    public function get_qualified_parent_key_name()
    {
        return $this->parent->qualify_column($this->second_local_key);
    }
    /**
     * Determine whether "through" parent of the relation uses Soft Deletes.
     *
     * @return bool
     */
    public function through_parent_soft_deletes()
    {
        return $this->through_parent::is_soft_deletable();
    }
    /**
     * Indicate that trashed "through" parents should be included in the query.
     *
     * @return $this
     */
    public function with_trashed_parents()
    {
        $this->query->without_global_scope('SoftDeletableHasManyThrough');
        return $this;
    }
    /** @inheritDoc */
    public function add_eager_constraints(array $models): void
    {
        $where_in = $this->where_in_method($this->far_parent, $this->local_key);
        $this->where_in_eager($where_in, $this->get_qualified_first_key_name(), $this->get_keys($models, $this->local_key), $this->get_relation_query());
    }
    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @return array<array<array-key, TRelatedModel>>
     */
    protected function build_dictionary(Eloquent_Collection $results)
    {
        $dictionary = [];
        $is_associative = Arr::is_assoc($results->all());
        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly access all of the related
        // models without having to do nested looping which will be quite slow.
        foreach ($results as $key => $result) {
            if ($is_associative) {
                $dictionary[$result->laravel_through_key][$key] = $result;
            } else {
                $dictionary[$result->laravel_through_key][] = $result;
            }
        }
        return $dictionary;
    }
    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @return TRelatedModel
     */
    public function first_or_new(array $attributes = [], array $values = [])
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }
        return $this->related->new_instance(array_merge($attributes, $values));
    }
    /**
     * Get the first record matching the attributes. If the record is not found, create it.
     *
     * @param  (\Closure(): array)|array  $values
     * @return TRelatedModel
     */
    public function first_or_create(array $attributes = [], Closure|array $values = [])
    {
        if (!is_null($instance = (clone $this)->where($attributes)->first())) {
            return $instance;
        }
        return $this->create_or_first(array_merge($attributes, value($values)));
    }
    /**
     * Attempt to create the record. If a unique constraint violation occurs, attempt to find the matching record.
     *
     * @param  (\Closure(): array)|array  $values
     * @return TRelatedModel
     */
    public function create_or_first(array $attributes = [], Closure|array $values = [])
    {
        try {
            return $this->get_query()->with_savepoint_if_needed(fn() => $this->create(array_merge($attributes, value($values))));
        } catch (Unique_Constraint_Violation_Exception $exception) {
            return $this->where($attributes)->first() ?? throw $exception;
        }
    }
    /**
     * Create or update a related record matching the attributes, and fill it with values.
     *
     * @return TRelatedModel
     */
    public function update_or_create(array $attributes, array $values = [])
    {
        return tap($this->first_or_create($attributes, $values), function ($instance) use ($values): void {
            if (!$instance->was_recently_created) {
                $instance->fill($values)->save();
            }
        });
    }
    /**
     * Add a basic where clause to the query, and return the first result.
     *
     * @param  \Closure|string|array  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return TRelatedModel|null
     */
    public function first_where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return $this->where($column, $operator, $value, $boolean)->first();
    }
    /**
     * Execute the query and get the first related model.
     *
     * @param  array  $columns
     * @return TRelatedModel|null
     */
    public function first($columns = ['*'])
    {
        $results = $this->limit(1)->get($columns);
        return count($results) > 0 ? $results->first() : null;
    }
    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array  $columns
     * @return TRelatedModel
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TRelatedModel>
     */
    public function first_or_fail($columns = ['*'])
    {
        if (!is_null($model = $this->first($columns))) {
            return $model;
        }
        throw (new Model_Not_Found_Exception())->set_model($this->related::class);
    }
    /**
     * Execute the query and get the first result or call a callback.
     *
     * @template TValue
     *
     * @param  (\Closure(): TValue)|list<string>  $columns
     * @param  (\Closure(): TValue)|null  $callback
     * @return TRelatedModel|TValue
     */
    public function first_or($columns = ['*'], ?Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            $callback = $columns;
            $columns = ['*'];
        }
        if (!is_null($model = $this->first($columns))) {
            return $model;
        }
        return $callback();
    }
    /**
     * Find a related model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? \Illuminate\Database\Eloquent\Collection<int, TRelatedModel> : TRelatedModel|null)
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->find_many($id, $columns);
        }
        return $this->where($this->get_related()->get_qualified_key_name(), '=', $id)->first($columns);
    }
    /**
     * Find a sole related model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return TRelatedModel
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TRelatedModel>
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function find_sole($id, $columns = ['*'])
    {
        return $this->where($this->get_related()->get_qualified_key_name(), '=', $id)->sole($columns);
    }
    /**
     * Find multiple related models by their primary keys.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $ids
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    public function find_many($ids, $columns = ['*'])
    {
        $ids = $ids instanceof Arrayable ? $ids->to_array() : $ids;
        if (empty($ids)) {
            return $this->get_related()->new_collection();
        }
        return $this->where_in($this->get_related()->get_qualified_key_name(), $ids)->get($columns);
    }
    /**
     * Find a related model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? \Illuminate\Database\Eloquent\Collection<int, TRelatedModel> : TRelatedModel)
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TRelatedModel>
     */
    public function find_or_fail($id, $columns = ['*'])
    {
        $result = $this->find($id, $columns);
        $id = $id instanceof Arrayable ? $id->to_array() : $id;
        if (is_array($id)) {
            if (count($result) === count(array_unique($id))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }
        throw (new Model_Not_Found_Exception())->set_model($this->related::class, $id);
    }
    /**
     * Find a related model by its primary key or call a callback.
     *
     * @template TValue
     *
     * @param  mixed  $id
     * @param  (\Closure(): TValue)|list<string>|string  $columns
     * @param  (\Closure(): TValue)|null  $callback
     * @return (
     *     $id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>)
     *     ? \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>|TValue
     *     : TRelatedModel|TValue
     * )
     */
    public function find_or($id, $columns = ['*'], ?Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            $callback = $columns;
            $columns = ['*'];
        }
        $result = $this->find($id, $columns);
        $id = $id instanceof Arrayable ? $id->to_array() : $id;
        if (is_array($id)) {
            if (count($result) === count(array_unique($id))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }
        return $callback();
    }
    /** @inheritDoc */
    public function get($columns = ['*'])
    {
        $builder = $this->prepare_query_builder($columns);
        $models = $builder->get_models();
        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eager_load_relations($models);
        }
        return $this->query->apply_after_query_callbacks($this->related->new_collection($models));
    }
    /**
     * Get a paginator for the "select" statement.
     *
     * @param  int|null  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($per_page = null, $columns = ['*'], $page_name = 'page', $page = null)
    {
        $this->query->add_select($this->should_select($columns));
        return $this->query->paginate($per_page, $columns, $page_name, $page);
    }
    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int|null  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simple_paginate($per_page = null, $columns = ['*'], $page_name = 'page', $page = null)
    {
        $this->query->add_select($this->should_select($columns));
        return $this->query->simple_paginate($per_page, $columns, $page_name, $page);
    }
    /**
     * Paginate the given query into a cursor paginator.
     *
     * @param  int|null  $perPage
     * @param  array  $columns
     * @param  string  $cursorName
     * @param  string|null  $cursor
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    public function cursor_paginate($per_page = null, $columns = ['*'], $cursor_name = 'cursor', $cursor = null)
    {
        $this->query->add_select($this->should_select($columns));
        return $this->query->cursor_paginate($per_page, $columns, $cursor_name, $cursor);
    }
    /**
     * Set the select clause for the relation query.
     *
     * @return array
     */
    protected function should_select(array $columns = ['*'])
    {
        if ($columns == ['*']) {
            $columns = [$this->related->qualify_column('*')];
        }
        return array_merge($columns, [$this->get_qualified_first_key_name() . ' as laravel_through_key']);
    }
    /**
     * Chunk the results of the query.
     *
     * @param  int  $count
     * @return bool
     */
    public function chunk($count, callable $callback)
    {
        return $this->prepare_query_builder()->chunk($count, $callback);
    }
    /**
     * Chunk the results of a query by comparing numeric IDs.
     *
     * @param  int  $count
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return bool
     */
    public function chunk_by_id($count, callable $callback, $column = null, $alias = null)
    {
        $column ??= $this->get_related()->get_qualified_key_name();
        $alias ??= $this->get_related()->get_key_name();
        return $this->prepare_query_builder()->chunk_by_id($count, $callback, $column, $alias);
    }
    /**
     * Chunk the results of a query by comparing IDs in descending order.
     *
     * @param  int  $count
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return bool
     */
    public function chunk_by_id_desc($count, callable $callback, $column = null, $alias = null)
    {
        $column ??= $this->get_related()->get_qualified_key_name();
        $alias ??= $this->get_related()->get_key_name();
        return $this->prepare_query_builder()->chunk_by_id_desc($count, $callback, $column, $alias);
    }
    /**
     * Execute a callback over each item while chunking by ID.
     *
     * @param  int  $count
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return bool
     */
    public function each_by_id(callable $callback, $count = 1000, $column = null, $alias = null)
    {
        $column ??= $this->get_related()->get_qualified_key_name();
        $alias ??= $this->get_related()->get_key_name();
        return $this->prepare_query_builder()->each_by_id($callback, $count, $column, $alias);
    }
    /**
     * Get a generator for the given query.
     *
     * @return \Illuminate\Support\LazyCollection<int, TRelatedModel>
     */
    public function cursor()
    {
        return $this->prepare_query_builder()->cursor();
    }
    /**
     * Execute a callback over each item while chunking.
     *
     * @param  int  $count
     * @return bool
     */
    public function each(callable $callback, $count = 1000)
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }
    /**
     * Query lazily, by chunks of the given size.
     *
     * @param  int  $chunkSize
     * @return \Illuminate\Support\LazyCollection<int, TRelatedModel>
     */
    public function lazy($chunk_size = 1000)
    {
        return $this->prepare_query_builder()->lazy($chunk_size);
    }
    /**
     * Query lazily, by chunking the results of a query by comparing IDs.
     *
     * @param  int  $chunkSize
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return \Illuminate\Support\LazyCollection<int, TRelatedModel>
     */
    public function lazy_by_id($chunk_size = 1000, $column = null, $alias = null)
    {
        $column ??= $this->get_related()->get_qualified_key_name();
        $alias ??= $this->get_related()->get_key_name();
        return $this->prepare_query_builder()->lazy_by_id($chunk_size, $column, $alias);
    }
    /**
     * Query lazily, by chunking the results of a query by comparing IDs in descending order.
     *
     * @param  int  $chunkSize
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return \Illuminate\Support\LazyCollection<int, TRelatedModel>
     */
    public function lazy_by_id_desc($chunk_size = 1000, $column = null, $alias = null)
    {
        $column ??= $this->get_related()->get_qualified_key_name();
        $alias ??= $this->get_related()->get_key_name();
        return $this->prepare_query_builder()->lazy_by_id_desc($chunk_size, $column, $alias);
    }
    /**
     * Prepare the query builder for query execution.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    protected function prepare_query_builder($columns = ['*'])
    {
        $builder = $this->query->apply_scopes();
        return $builder->add_select($this->should_select($builder->get_query()->columns ? [] : $columns));
    }
    /** @inheritDoc */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        if ($parent_query->get_query()->from === $query->get_query()->from) {
            return $this->get_relation_existence_query_for_self_relation($query, $parent_query, $columns);
        }
        if ($parent_query->get_query()->from === $this->through_parent->get_table()) {
            return $this->get_relation_existence_query_for_through_self_relation($query, $parent_query, $columns);
        }
        $this->perform_join($query);
        return $query->select($columns)->where_column($this->get_qualified_local_key_name(), '=', $this->get_qualified_first_key_name());
    }
    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function get_relation_existence_query_for_self_relation(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        $query->from($query->get_model()->get_table() . ' as ' . $hash = $this->get_relation_count_hash());
        $query->join($this->through_parent->get_table(), $this->get_qualified_parent_key_name(), '=', $hash . '.' . $this->second_key);
        if ($this->through_parent_soft_deletes()) {
            $query->where_null($this->through_parent->get_qualified_deleted_at_column());
        }
        $query->get_model()->set_table($hash);
        return $query->select($columns)->where_column($parent_query->get_query()->from . '.' . $this->local_key, '=', $this->get_qualified_first_key_name());
    }
    /**
     * Add the constraints for a relationship query on the same table as the through parent.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function get_relation_existence_query_for_through_self_relation(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        $table = $this->through_parent->get_table() . ' as ' . $hash = $this->get_relation_count_hash();
        $query->join($table, $hash . '.' . $this->second_local_key, '=', $this->get_qualified_far_key_name());
        if ($this->through_parent_soft_deletes()) {
            $query->where_null($hash . '.' . $this->through_parent->get_deleted_at_column());
        }
        return $query->select($columns)->where_column($parent_query->get_query()->from . '.' . $this->local_key, '=', $hash . '.' . $this->first_key);
    }
    /**
     * Alias to set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function take($value)
    {
        return $this->limit($value);
    }
    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit($value)
    {
        if ($this->far_parent->exists) {
            $this->query->limit($value);
        } else {
            $column = $this->get_qualified_first_key_name();
            $grammar = $this->query->get_query()->get_grammar();
            if ($grammar instanceof My_Sql_Grammar && $grammar->use_legacy_group_limit($this->query->get_query())) {
                $column = 'laravel_through_key';
            }
            $this->query->group_limit($value, $column);
        }
        return $this;
    }
    /**
     * Get the qualified foreign key on the related model.
     *
     * @return string
     */
    public function get_qualified_far_key_name()
    {
        return $this->get_qualified_foreign_key_name();
    }
    /**
     * Get the foreign key on the "through" model.
     *
     * @return string
     */
    public function get_first_key_name()
    {
        return $this->first_key;
    }
    /**
     * Get the qualified foreign key on the "through" model.
     *
     * @return string
     */
    public function get_qualified_first_key_name()
    {
        return $this->through_parent->qualify_column($this->first_key);
    }
    /**
     * Get the foreign key on the related model.
     *
     * @return string
     */
    public function get_foreign_key_name()
    {
        return $this->second_key;
    }
    /**
     * Get the qualified foreign key on the related model.
     *
     * @return string
     */
    public function get_qualified_foreign_key_name()
    {
        return $this->related->qualify_column($this->second_key);
    }
    /**
     * Get the local key on the far parent model.
     *
     * @return string
     */
    public function get_local_key_name()
    {
        return $this->local_key;
    }
    /**
     * Get the qualified local key on the far parent model.
     *
     * @return string
     */
    public function get_qualified_local_key_name()
    {
        return $this->far_parent->qualify_column($this->local_key);
    }
    /**
     * Get the local key on the intermediary model.
     *
     * @return string
     */
    public function get_second_local_key_name()
    {
        return $this->second_local_key;
    }
}