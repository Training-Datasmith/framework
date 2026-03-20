<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model_Not_Found_Exception;
use Illuminate\Database\Eloquent\Relations\Concerns\As_Pivot;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Dictionary;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Pivot_Table;
use Illuminate\Database\Query\Grammars\My_Sql_Grammar;
use Illuminate\Database\Unique_Constraint_Violation_Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use InvalidArgumentException;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TPivotModel of \Illuminate\Database\Eloquent\Relations\Pivot = \Illuminate\Database\Eloquent\Relations\Pivot
 * @template TAccessor of string = 'pivot'
 *
 * @extends \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, TDeclaringModel, \Illuminate\Database\Eloquent\Collection<int, TRelatedModel&object{pivot: TPivotModel}>>
 *
 * @todo use TAccessor when PHPStan bug is fixed: https://github.com/phpstan/phpstan/issues/12756
 */
class Belongs_To_Many extends Relation
{
    use Interacts_With_Dictionary;
    use Interacts_With_Pivot_Table;
    /**
     * The intermediate table for the relation.
     *
     * @var string
     */
    protected $table;
    /**
     * The pivot table columns to retrieve.
     *
     * @var array<string|\Illuminate\Contracts\Database\Query\Expression>
     */
    protected $pivot_columns = [];
    /**
     * Any pivot table restrictions for where clauses.
     *
     * @var array
     */
    protected $pivot_wheres = [];
    /**
     * Any pivot table restrictions for whereIn clauses.
     *
     * @var array
     */
    protected $pivot_where_ins = [];
    /**
     * Any pivot table restrictions for whereNull clauses.
     *
     * @var array
     */
    protected $pivot_where_nulls = [];
    /**
     * The default values for the pivot columns.
     *
     * @var array
     */
    protected $pivot_values = [];
    /**
     * Indicates if timestamps are available on the pivot table.
     *
     * @var bool
     */
    public $with_timestamps = false;
    /**
     * The custom pivot table column for the created_at timestamp.
     *
     * @var string|null
     */
    protected $pivot_created_at;
    /**
     * The custom pivot table column for the updated_at timestamp.
     *
     * @var string|null
     */
    protected $pivot_updated_at;
    /**
     * The class name of the custom pivot model to use for the relationship.
     *
     * @var class-string<TPivotModel>
     */
    protected $using;
    /**
     * The name of the accessor to use for the "pivot" relationship.
     *
     * @var TAccessor
     */
    protected $accessor = 'pivot';
    /**
     * Create a new belongs to many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string|class-string<TRelatedModel>  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $table,
        /**
         * The foreign key of the parent model.
         */
        protected $foreign_pivot_key,
        /**
         * The associated key of the relation.
         */
        protected $related_pivot_key,
        /**
         * The key name of the parent model.
         */
        protected $parent_key,
        /**
         * The key name of the related model.
         */
        protected $related_key,
        /**
         * The "name" of the relationship.
         */
        protected $relation_name = null
    )
    {
        $this->table = $this->resolve_table_name($table);
        parent::__construct($query, $parent);
    }
    /**
     * Attempt to resolve the intermediate table name from the given string.
     *
     * @param  string  $table
     * @return string
     */
    protected function resolve_table_name($table)
    {
        if (!str_contains($table, '\\') || !class_exists($table)) {
            return $table;
        }
        $model = new $table();
        if (!$model instanceof Model) {
            return $table;
        }
        if (in_array(As_Pivot::class, class_uses_recursive($model))) {
            $this->using($table);
        }
        return $model->get_table();
    }
    /**
     * Set the base constraints on the relation query.
     */
    public function add_constraints(): void
    {
        $this->perform_join();
        if (static::$constraints) {
            $this->add_where_constraints();
        }
    }
    /**
     * Set the join clause for the relation query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>|null  $query
     * @return $this
     */
    protected function perform_join($query = null): static
    {
        $query = $query ?: $this->query;
        // We need to join to the intermediate table on the related model's primary
        // key column with the intermediate table's foreign key for the related
        // model instance. Then we can set the "where" for the parent models.
        $query->join($this->table, $this->get_qualified_related_key_name(), '=', $this->get_qualified_related_pivot_key_name());
        return $this;
    }
    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function add_where_constraints(): static
    {
        $this->query->where($this->get_qualified_foreign_pivot_key_name(), '=', $this->parent->{$this->parent_key});
        return $this;
    }
    /** @inheritDoc */
    public function add_eager_constraints(array $models): void
    {
        $where_in = $this->where_in_method($this->parent, $this->parent_key);
        $this->where_in_eager($where_in, $this->get_qualified_foreign_pivot_key_name(), $this->get_keys($models, $this->parent_key));
    }
    /** @inheritDoc */
    public function init_relation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->set_relation($relation, $this->related->new_collection());
        }
        return $models;
    }
    /** @inheritDoc */
    public function match(array $models, Eloquent_Collection $results, $relation): array
    {
        $dictionary = $this->build_dictionary($results);
        // Once we have an array dictionary of child objects we can easily match the
        // children back to their parent using the dictionary and the keys on the
        // parent models. Then we should return these hydrated models back out.
        foreach ($models as $model) {
            $key = $this->get_dictionary_key($model->{$this->parent_key});
            if ($key !== null && isset($dictionary[$key])) {
                $model->set_relation($relation, $this->related->new_collection($dictionary[$key]));
            }
        }
        return $models;
    }
    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @return array<array<array-key, TRelatedModel>>
     */
    protected function build_dictionary(Eloquent_Collection $results): array
    {
        // First we'll build a dictionary of child models keyed by the foreign key
        // of the relation so that we will easily and quickly match them to the
        // parents without having a possibly slow inner loop for every model.
        $dictionary = [];
        $is_associative = Arr::is_assoc($results->all());
        foreach ($results as $key => $result) {
            $value = $this->get_dictionary_key($result->{$this->accessor}->{$this->foreign_pivot_key});
            if ($value === null) {
                continue;
            }
            if ($is_associative) {
                $dictionary[$value][$key] = $result;
            } else {
                $dictionary[$value][] = $result;
            }
        }
        return $dictionary;
    }
    /**
     * Get the class being used for pivot models.
     *
     * @return class-string<TPivotModel>
     */
    public function get_pivot_class()
    {
        return $this->using ?? Pivot::class;
    }
    /**
     * Specify the custom pivot model to use for the relationship.
     *
     * @template TNewPivotModel of \Illuminate\Database\Eloquent\Relations\Pivot
     *
     * @param  class-string<TNewPivotModel>  $class
     * @return $this
     *
     * @phpstan-this-out static<TRelatedModel, TDeclaringModel, TNewPivotModel, TAccessor>
     */
    public function using($class): static
    {
        $this->using = $class;
        return $this;
    }
    /**
     * Specify the custom pivot accessor to use for the relationship.
     *
     * @template TNewAccessor of string
     *
     * @param  TNewAccessor  $accessor
     * @return $this
     *
     * @phpstan-this-out static<TRelatedModel, TDeclaringModel, TPivotModel, TNewAccessor>
     */
    public function as($accessor): static
    {
        $this->accessor = $accessor;
        return $this;
    }
    /**
     * Set a where clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_pivot($column, $operator = null, $value = null, $boolean = 'and'): \Illuminate\Database\Eloquent\Builder
    {
        $this->pivot_wheres[] = func_get_args();
        return $this->where($this->qualify_pivot_column($column), $operator, $value, $boolean);
    }
    /**
     * Set a "where between" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_pivot_between($column, array $values, $boolean = 'and', $not = false)
    {
        return $this->where_between($this->qualify_pivot_column($column), $values, $boolean, $not);
    }
    /**
     * Set a "or where between" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return $this
     */
    public function or_where_pivot_between($column, array $values)
    {
        return $this->where_pivot_between($column, $values, 'or');
    }
    /**
     * Set a "where pivot not between" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string  $boolean
     * @return $this
     */
    public function where_pivot_not_between($column, array $values, $boolean = 'and')
    {
        return $this->where_pivot_between($column, $values, $boolean, true);
    }
    /**
     * Set a "or where not between" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return $this
     */
    public function or_where_pivot_not_between($column, array $values)
    {
        return $this->where_pivot_between($column, $values, 'or', true);
    }
    /**
     * Set a "where in" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_pivot_in($column, $values, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
    {
        $this->pivot_where_ins[] = func_get_args();
        return $this->where_in($this->qualify_pivot_column($column), $values, $boolean, $not);
    }
    /**
     * Set an "or where" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_pivot($column, $operator = null, $value = null)
    {
        return $this->where_pivot($column, $operator, $value, 'or');
    }
    /**
     * Set a where clause for a pivot table column.
     *
     * In addition, new pivot records will receive this value.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression|array<string, string>  $column
     * @param  mixed  $value
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function with_pivot_value($column, $value = null)
    {
        if (is_array($column)) {
            foreach ($column as $name => $value) {
                $this->with_pivot_value($name, $value);
            }
            return $this;
        }
        if (is_null($value)) {
            throw new InvalidArgumentException('The provided value may not be null.');
        }
        $this->pivot_values[] = compact('column', 'value');
        return $this->where_pivot($column, '=', $value);
    }
    /**
     * Set an "or where in" clause for a pivot table column.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @return $this
     */
    public function or_where_pivot_in($column, $values)
    {
        return $this->where_pivot_in($column, $values, 'or');
    }
    /**
     * Set a "where not in" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @return $this
     */
    public function where_pivot_not_in($column, $values, $boolean = 'and')
    {
        return $this->where_pivot_in($column, $values, $boolean, true);
    }
    /**
     * Set an "or where not in" clause for a pivot table column.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @return $this
     */
    public function or_where_pivot_not_in($column, $values)
    {
        return $this->where_pivot_not_in($column, $values, 'or');
    }
    /**
     * Set a "where null" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_pivot_null($column, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
    {
        $this->pivot_where_nulls[] = func_get_args();
        return $this->where_null($this->qualify_pivot_column($column), $boolean, $not);
    }
    /**
     * Set a "where not null" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string  $boolean
     * @return $this
     */
    public function where_pivot_not_null($column, $boolean = 'and')
    {
        return $this->where_pivot_null($column, $boolean, true);
    }
    /**
     * Set a "or where null" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  bool  $not
     * @return $this
     */
    public function or_where_pivot_null($column, $not = false)
    {
        return $this->where_pivot_null($column, 'or', $not);
    }
    /**
     * Set a "or where not null" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return $this
     */
    public function or_where_pivot_not_null($column)
    {
        return $this->or_where_pivot_null($column, true);
    }
    /**
     * Add an "order by" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string  $direction
     * @return $this
     */
    public function order_by_pivot($column, $direction = 'asc'): \Illuminate\Database\Query\Builder
    {
        return $this->order_by($this->qualify_pivot_column($column), $direction);
    }
    /**
     * Add an "order by desc" clause for a pivot table column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return $this
     */
    public function order_by_pivot_desc($column): \Illuminate\Database\Query\Builder
    {
        return $this->order_by($this->qualify_pivot_column($column), 'desc');
    }
    /**
     * Find a related model by its primary key or return a new instance of the related model.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return (
     *     $id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>)
     *     ? \Illuminate\Database\Eloquent\Collection<int, TRelatedModel&object{pivot: TPivotModel}>
     *     : TRelatedModel&object{pivot: TPivotModel}
     * )
     */
    public function find_or_new($id, $columns = ['*'])
    {
        if (is_null($instance = $this->find($id, $columns))) {
            return $this->related->new_instance();
        }
        return $instance;
    }
    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function first_or_new(array $attributes = [], array $values = [])
    {
        if (is_null($instance = $this->related->where($attributes)->first())) {
            return $this->related->new_instance(array_merge($attributes, $values));
        }
        return $instance;
    }
    /**
     * Get the first record matching the attributes. If the record is not found, create it.
     *
     * @param  (\Closure(): array)|array  $values
     * @param  bool  $touch
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function first_or_create(array $attributes = [], Closure|array $values = [], array $joining = [], $touch = true)
    {
        if (is_null($instance = (clone $this)->where($attributes)->first())) {
            if (is_null($instance = $this->related->where($attributes)->first())) {
                $instance = $this->create_or_first($attributes, $values, $joining, $touch);
            } else {
                try {
                    $this->get_query()->with_savepoint_if_needed(fn() => $this->attach($instance, $joining, $touch));
                } catch (Unique_Constraint_Violation_Exception) {
                    // Nothing to do, the model was already attached...
                }
            }
        }
        return $instance;
    }
    /**
     * Attempt to create the record. If a unique constraint violation occurs, attempt to find the matching record.
     *
     * @param  (\Closure(): array)|array  $values
     * @param  bool  $touch
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function create_or_first(array $attributes = [], Closure|array $values = [], array $joining = [], $touch = true)
    {
        try {
            return $this->get_query()->with_savepoint_if_needed(fn() => $this->create(array_merge($attributes, value($values)), $joining, $touch));
        } catch (Unique_Constraint_Violation_Exception $e) {
            // ...
        }
        try {
            return tap($this->related->where($attributes)->first() ?? throw $e, function ($instance) use ($joining, $touch): void {
                $this->get_query()->with_savepoint_if_needed(fn() => $this->attach($instance, $joining, $touch));
            });
        } catch (Unique_Constraint_Violation_Exception $e) {
            return (clone $this)->use_write_pdo()->where($attributes)->first() ?? throw $e;
        }
    }
    /**
     * Create or update a related record matching the attributes, and fill it with values.
     *
     * @param  bool  $touch
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function update_or_create(array $attributes, array $values = [], array $joining = [], $touch = true)
    {
        return tap($this->first_or_create($attributes, $values, $joining, $touch), function ($instance) use ($values): void {
            if (!$instance->was_recently_created) {
                $instance->fill($values);
                $instance->save(['touch' => false]);
            }
        });
    }
    /**
     * Find a related model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return (
     *     $id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>)
     *     ? \Illuminate\Database\Eloquent\Collection<int, TRelatedModel&object{pivot: TPivotModel}>
     *     : (TRelatedModel&object{pivot: TPivotModel})|null
     * )
     */
    public function find($id, $columns = ['*'])
    {
        if (!$id instanceof Model && (is_array($id) || $id instanceof Arrayable)) {
            return $this->find_many($id, $columns);
        }
        return $this->where($this->get_related()->get_qualified_key_name(), '=', $this->parse_id($id))->first($columns);
    }
    /**
     * Find a sole related model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return TRelatedModel&object{pivot: TPivotModel}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TRelatedModel>
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function find_sole($id, $columns = ['*'])
    {
        return $this->where($this->get_related()->get_qualified_key_name(), '=', $this->parse_id($id))->sole($columns);
    }
    /**
     * Find multiple related models by their primary keys.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $ids
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function find_many($ids, $columns = ['*'])
    {
        $ids = $ids instanceof Arrayable ? $ids->to_array() : $ids;
        if (empty($ids)) {
            return $this->get_related()->new_collection();
        }
        return $this->where_key($this->parse_ids($ids))->get($columns);
    }
    /**
     * Find a related model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return (
     *     $id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>)
     *     ? \Illuminate\Database\Eloquent\Collection<int, TRelatedModel&object{pivot: TPivotModel}>
     *     : TRelatedModel&object{pivot: TPivotModel}
     * )
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
     *     ? \Illuminate\Database\Eloquent\Collection<int, TRelatedModel&object{pivot: TPivotModel}>|TValue
     *     : (TRelatedModel&object{pivot: TPivotModel})|TValue
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
    /**
     * Add a basic where clause to the query, and return the first result.
     *
     * @param  \Closure|string|array  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return (TRelatedModel&object{pivot: TPivotModel})|null
     */
    public function first_where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return $this->where($column, $operator, $value, $boolean)->first();
    }
    /**
     * Execute the query and get the first result.
     *
     * @param  array  $columns
     * @return (TRelatedModel&object{pivot: TPivotModel})|null
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
     * @return TRelatedModel&object{pivot: TPivotModel}
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
     * @return (TRelatedModel&object{pivot: TPivotModel})|TValue
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
    /** @inheritDoc */
    public function get_results()
    {
        return !is_null($this->parent->{$this->parent_key}) ? $this->get() : $this->related->new_collection();
    }
    /** @inheritDoc */
    public function get($columns = ['*'])
    {
        // First we'll add the proper select columns onto the query so it is run with
        // the proper columns. Then, we will get the results and hydrate our pivot
        // models with the result of those columns as a separate model relation.
        $builder = $this->query->apply_scopes();
        $columns = $builder->get_query()->columns ? [] : $columns;
        $models = $builder->add_select($this->should_select($columns))->get_models();
        $this->hydrate_pivot_relation($models);
        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eager_load_relations($models);
        }
        return $this->query->apply_after_query_callbacks($this->related->new_collection($models));
    }
    /**
     * Get the select columns for the relation query.
     */
    protected function should_select(array $columns = ['*']): array
    {
        if ($columns == ['*']) {
            $columns = [$this->related->qualify_column('*')];
        }
        return array_merge($columns, $this->aliased_pivot_columns());
    }
    /**
     * Get the pivot columns for the relation.
     *
     * "pivot_" is prefixed at each column for easy removal later.
     *
     * @return array
     */
    protected function aliased_pivot_columns()
    {
        return (new Base_Collection([$this->foreign_pivot_key, $this->related_pivot_key, ...$this->pivot_columns]))->map(fn($column) => $this->qualify_pivot_column($column) . ' as pivot_' . $column)->unique()->all();
    }
    /**
     * Get a paginator for the "select" statement.
     *
     * @param  int|null  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function paginate($per_page = null, $columns = ['*'], $page_name = 'page', $page = null)
    {
        $this->query->add_select($this->should_select($columns));
        return tap($this->query->paginate($per_page, $columns, $page_name, $page), function ($paginator): void {
            $this->hydrate_pivot_relation($paginator->items());
        });
    }
    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int|null  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function simple_paginate($per_page = null, $columns = ['*'], $page_name = 'page', $page = null)
    {
        $this->query->add_select($this->should_select($columns));
        return tap($this->query->simple_paginate($per_page, $columns, $page_name, $page), function ($paginator): void {
            $this->hydrate_pivot_relation($paginator->items());
        });
    }
    /**
     * Paginate the given query into a cursor paginator.
     *
     * @param  int|null  $perPage
     * @param  array  $columns
     * @param  string  $cursorName
     * @param  string|null  $cursor
     * @return \Illuminate\Contracts\Pagination\CursorPaginator<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function cursor_paginate($per_page = null, $columns = ['*'], $cursor_name = 'cursor', $cursor = null)
    {
        $this->query->add_select($this->should_select($columns));
        return tap($this->query->cursor_paginate($per_page, $columns, $cursor_name, $cursor), function ($paginator): void {
            $this->hydrate_pivot_relation($paginator->items());
        });
    }
    /**
     * Chunk the results of the query.
     *
     * @param  int  $count
     */
    public function chunk($count, callable $callback): bool
    {
        return $this->prepare_query_builder()->chunk($count, function ($results, $page) use ($callback) {
            $this->hydrate_pivot_relation($results->all());
            return $callback($results, $page);
        });
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
        return $this->ordered_chunk_by_id($count, $callback, $column, $alias);
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
        return $this->ordered_chunk_by_id($count, $callback, $column, $alias, descending: true);
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
        return $this->chunk_by_id($count, function ($results, $page) use ($callback, $count) {
            foreach ($results as $key => $value) {
                if ($callback($value, ($page - 1) * $count + $key) === false) {
                    return false;
                }
            }
        }, $column, $alias);
    }
    /**
     * Chunk the results of a query by comparing IDs in a given order.
     *
     * @param  int  $count
     * @param  string|null  $column
     * @param  string|null  $alias
     * @param  bool  $descending
     */
    public function ordered_chunk_by_id($count, callable $callback, $column = null, $alias = null, $descending = false): bool
    {
        $column ??= $this->get_related()->qualify_column($this->get_related_key_name());
        $alias ??= $this->get_related_key_name();
        return $this->prepare_query_builder()->ordered_chunk_by_id($count, function ($results, $page) use ($callback) {
            $this->hydrate_pivot_relation($results->all());
            return $callback($results, $page);
        }, $column, $alias, $descending);
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
     * @return \Illuminate\Support\LazyCollection<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function lazy($chunk_size = 1000): \Illuminate\Support\Lazy_Collection
    {
        return $this->prepare_query_builder()->lazy($chunk_size)->map(function ($model): \Illuminate\Database\Eloquent\Model {
            $this->hydrate_pivot_relation([$model]);
            return $model;
        });
    }
    /**
     * Query lazily, by chunking the results of a query by comparing IDs.
     *
     * @param  int  $chunkSize
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return \Illuminate\Support\LazyCollection<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function lazy_by_id($chunk_size = 1000, $column = null, $alias = null): \Illuminate\Support\Lazy_Collection
    {
        $column ??= $this->get_related()->qualify_column($this->get_related_key_name());
        $alias ??= $this->get_related_key_name();
        return $this->prepare_query_builder()->lazy_by_id($chunk_size, $column, $alias)->map(function ($model): \Illuminate\Database\Eloquent\Model {
            $this->hydrate_pivot_relation([$model]);
            return $model;
        });
    }
    /**
     * Query lazily, by chunking the results of a query by comparing IDs in descending order.
     *
     * @param  int  $chunkSize
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return \Illuminate\Support\LazyCollection<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function lazy_by_id_desc($chunk_size = 1000, $column = null, $alias = null): \Illuminate\Support\Lazy_Collection
    {
        $column ??= $this->get_related()->qualify_column($this->get_related_key_name());
        $alias ??= $this->get_related_key_name();
        return $this->prepare_query_builder()->lazy_by_id_desc($chunk_size, $column, $alias)->map(function ($model): \Illuminate\Database\Eloquent\Model {
            $this->hydrate_pivot_relation([$model]);
            return $model;
        });
    }
    /**
     * Get a lazy collection for the given query.
     *
     * @return \Illuminate\Support\LazyCollection<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function cursor(): \Illuminate\Support\Lazy_Collection
    {
        return $this->prepare_query_builder()->cursor()->map(function ($model): \Illuminate\Database\Eloquent\Model {
            $this->hydrate_pivot_relation([$model]);
            return $model;
        });
    }
    /**
     * Prepare the query builder for query execution.
     *
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    protected function prepare_query_builder(): \Illuminate\Database\Query\Builder
    {
        return $this->query->add_select($this->should_select());
    }
    /**
     * Hydrate the pivot table relationship on the models.
     *
     * @param  array<int, TRelatedModel>  $models
     * @return void
     */
    protected function hydrate_pivot_relation(array $models)
    {
        // To hydrate the pivot relationship, we will just gather the pivot attributes
        // and create a new Pivot model, which is basically a dynamic model that we
        // will set the attributes, table, and connections on it so it will work.
        foreach ($models as $model) {
            $model->set_relation($this->accessor, $this->new_existing_pivot($this->migrate_pivot_attributes($model)));
        }
    }
    /**
     * Get the pivot attributes from a model.
     *
     * @param  TRelatedModel  $model
     */
    protected function migrate_pivot_attributes(Model $model): array
    {
        $values = [];
        foreach ($model->get_attributes() as $key => $value) {
            // To get the pivots attributes we will just take any of the attributes which
            // begin with "pivot_" and add those to this arrays, as well as unsetting
            // them from the parent's models since they exist in a different table.
            if (str_starts_with($key, 'pivot_')) {
                $values[substr($key, 6)] = $value;
                unset($model->{$key});
            }
        }
        return $values;
    }
    /**
     * If we're touching the parent model, touch.
     */
    public function touch_if_touching(): void
    {
        if ($this->touching_parent()) {
            $this->get_parent()->touch();
        }
        if ($this->get_parent()->touches($this->relation_name)) {
            $this->touch();
        }
    }
    /**
     * Determine if we should touch the parent on sync.
     */
    protected function touching_parent(): bool
    {
        return $this->get_related()->touches($this->guess_inverse_relation());
    }
    /**
     * Attempt to guess the name of the inverse of the relation.
     *
     * @return string
     */
    protected function guess_inverse_relation()
    {
        return Str::camel(Str::plural_studly(class_basename($this->get_parent())));
    }
    /**
     * Touch all of the related models for the relationship.
     *
     * E.g.: Touch all roles associated with this user.
     */
    public function touch(): void
    {
        if ($this->related->is_ignoring_touch()) {
            return;
        }
        $columns = [$this->related->get_updated_at_column() => $this->related->fresh_timestamp_string()];
        // If we actually have IDs for the relation, we will run the query to update all
        // the related model's timestamps, to make sure these all reflect the changes
        // to the parent models. This will help us keep any caching synced up here.
        if (count($ids = $this->all_related_ids()) > 0) {
            $this->get_related()->new_query_without_relationships()->where_key($ids)->update($columns);
        }
    }
    /**
     * Get all of the IDs for the related models.
     *
     * @return \Illuminate\Support\Collection<int, int|string>
     */
    public function all_related_ids()
    {
        return $this->new_pivot_query()->pluck($this->related_pivot_key);
    }
    /**
     * Save a new model and attach it to the parent model.
     *
     * @param  TRelatedModel  $model
     * @param  bool  $touch
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function save(Model $model, array $pivot_attributes = [], $touch = true): Model
    {
        $model->save(['touch' => false]);
        $this->attach($model, $pivot_attributes, $touch);
        return $model;
    }
    /**
     * Save a new model without raising any events and attach it to the parent model.
     *
     * @param  TRelatedModel  $model
     * @param  bool  $touch
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function save_quietly(Model $model, array $pivot_attributes = [], $touch = true)
    {
        return Model::without_events(fn(): \Illuminate\Database\Eloquent\Model => $this->save($model, $pivot_attributes, $touch));
    }
    /**
     * Save an array of new models and attach them to the parent model.
     *
     * @template TContainer of \Illuminate\Support\Collection<array-key, TRelatedModel>|array<array-key, TRelatedModel>
     *
     * @param  TContainer  $models
     * @return TContainer
     */
    public function save_many($models, array $pivot_attributes = [])
    {
        foreach ($models as $key => $model) {
            $this->save($model, (array) ($pivot_attributes[$key] ?? []), false);
        }
        $this->touch_if_touching();
        return $models;
    }
    /**
     * Save an array of new models without raising any events and attach them to the parent model.
     *
     * @template TContainer of \Illuminate\Support\Collection<array-key, TRelatedModel>|array<array-key, TRelatedModel>
     *
     * @param  TContainer  $models
     * @return TContainer
     */
    public function save_many_quietly($models, array $pivot_attributes = [])
    {
        return Model::without_events(fn() => $this->save_many($models, $pivot_attributes));
    }
    /**
     * Create a new instance of the related model.
     *
     * @param  bool  $touch
     * @return TRelatedModel&object{pivot: TPivotModel}
     */
    public function create(array $attributes = [], array $joining = [], $touch = true)
    {
        $attributes = array_merge($this->get_query()->pending_attributes, $attributes);
        $instance = $this->related->new_instance($attributes);
        // Once we save the related model, we need to attach it to the base model via
        // through intermediate table so we'll use the existing "attach" method to
        // accomplish this which will insert the record and any more attributes.
        $instance->save(['touch' => false]);
        $this->attach($instance, $joining, $touch);
        return $instance;
    }
    /**
     * Create an array of new instances of the related models.
     *
     * @return array<int, TRelatedModel&object{pivot: TPivotModel}>
     */
    public function create_many(iterable $records, array $joinings = []): array
    {
        $instances = [];
        foreach ($records as $key => $record) {
            $instances[] = $this->create($record, (array) ($joinings[$key] ?? []), false);
        }
        $this->touch_if_touching();
        return $instances;
    }
    /** @inheritDoc */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        if ($parent_query->get_query()->from == $query->get_query()->from) {
            return $this->get_relation_existence_query_for_self_join($query, $parent_query, $columns);
        }
        $this->perform_join($query);
        return parent::get_relation_existence_query($query, $parent_query, $columns);
    }
    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function get_relation_existence_query_for_self_join(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        $query->select($columns);
        $query->from($this->related->get_table() . ' as ' . $hash = $this->get_relation_count_hash());
        $this->related->set_table($hash);
        $this->perform_join($query);
        return parent::get_relation_existence_query($query, $parent_query, $columns);
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
        if ($this->parent->exists) {
            $this->query->limit($value);
        } else {
            $column = $this->get_existence_compare_key();
            $grammar = $this->query->get_query()->get_grammar();
            if ($grammar instanceof My_Sql_Grammar && $grammar->use_legacy_group_limit($this->query->get_query())) {
                $column = 'pivot_' . last(explode('.', $column));
            }
            $this->query->group_limit($value, $column);
        }
        return $this;
    }
    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function get_existence_compare_key()
    {
        return $this->get_qualified_foreign_pivot_key_name();
    }
    /**
     * Specify that the pivot table has creation and update timestamps.
     *
     * @param  string|null|false  $createdAt
     * @param  string|null|false  $updatedAt
     * @return $this
     */
    public function with_timestamps($created_at = null, $updated_at = null)
    {
        $this->pivot_created_at = $created_at !== false ? $created_at : null;
        $this->pivot_updated_at = $updated_at !== false ? $updated_at : null;
        $pivots = array_filter([$created_at !== false ? $this->created_at() : null, $updated_at !== false ? $this->updated_at() : null]);
        $this->with_timestamps = !empty($pivots);
        return $this->with_timestamps ? $this->with_pivot($pivots) : $this;
    }
    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function created_at()
    {
        return $this->pivot_created_at ?? $this->parent->get_created_at_column() ?? Model::CREATED_AT;
    }
    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function updated_at()
    {
        return $this->pivot_updated_at ?? $this->parent->get_updated_at_column() ?? Model::UPDATED_AT;
    }
    /**
     * Get the foreign key for the relation.
     *
     * @return string
     */
    public function get_foreign_pivot_key_name()
    {
        return $this->foreign_pivot_key;
    }
    /**
     * Get the fully-qualified foreign key for the relation.
     *
     * @return string
     */
    public function get_qualified_foreign_pivot_key_name()
    {
        return $this->qualify_pivot_column($this->foreign_pivot_key);
    }
    /**
     * Get the "related key" for the relation.
     *
     * @return string
     */
    public function get_related_pivot_key_name()
    {
        return $this->related_pivot_key;
    }
    /**
     * Get the fully-qualified "related key" for the relation.
     *
     * @return string
     */
    public function get_qualified_related_pivot_key_name()
    {
        return $this->qualify_pivot_column($this->related_pivot_key);
    }
    /**
     * Get the parent key for the relationship.
     *
     * @return string
     */
    public function get_parent_key_name()
    {
        return $this->parent_key;
    }
    /**
     * Get the fully-qualified parent key name for the relation.
     *
     * @return string
     */
    public function get_qualified_parent_key_name()
    {
        return $this->parent->qualify_column($this->parent_key);
    }
    /**
     * Get the related key for the relationship.
     *
     * @return string
     */
    public function get_related_key_name()
    {
        return $this->related_key;
    }
    /**
     * Get the fully-qualified related key name for the relation.
     *
     * @return string
     */
    public function get_qualified_related_key_name()
    {
        return $this->related->qualify_column($this->related_key);
    }
    /**
     * Get the intermediate table for the relationship.
     *
     * @return string
     */
    public function get_table()
    {
        return $this->table;
    }
    /**
     * Get the relationship name for the relationship.
     *
     * @return string
     */
    public function get_relation_name()
    {
        return $this->relation_name;
    }
    /**
     * Get the name of the pivot accessor for this relationship.
     *
     * @return TAccessor
     */
    public function get_pivot_accessor()
    {
        return $this->accessor;
    }
    /**
     * Get the pivot columns for this relationship.
     *
     * @return array
     */
    public function get_pivot_columns()
    {
        return $this->pivot_columns;
    }
    /**
     * Qualify the given column name by the pivot table.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return string|\Illuminate\Contracts\Database\Query\Expression
     */
    public function qualify_pivot_column($column)
    {
        if ($this->query->get_query()->get_grammar()->is_expression($column)) {
            return $column;
        }
        return str_contains($column, '.') ? $column : $this->table . '.' . $column;
    }
}