<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use BadMethodCallException;
use Closure;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Concerns\Builds_Queries;
use Illuminate\Database\Eloquent\Concerns\Queries_Relationships;
use Illuminate\Database\Eloquent\Relations\Belongs_To_Many;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Records_Not_Found_Exception;
use Illuminate\Database\Unique_Constraint_Violation_Exception;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Forwards_Calls;
use ReflectionClass;
use ReflectionMethod;
/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @property-read HigherOrderBuilderProxy|$this $orWhere
 * @property-read HigherOrderBuilderProxy|$this $whereNot
 * @property-read HigherOrderBuilderProxy|$this $orWhereNot
 *
 * @mixin \Illuminate\Database\Query\Builder
 */
class Builder implements Builder_Contract
{
    /** @use \Illuminate\Database\Concerns\BuildsQueries<TModel> */
    use Builds_Queries, Forwards_Calls, Queries_Relationships {
        Builds_Queries::sole as baseSole;
    }
    /**
     * The model being queried.
     *
     * @var TModel
     */
    protected $model;
    /**
     * The attributes that should be added to new models created by this builder.
     *
     * @var array
     */
    public $pending_attributes = [];
    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $eager_load = [];
    /**
     * All of the globally registered builder macros.
     *
     * @var array
     */
    protected static $macros = [];
    /**
     * All of the locally registered builder macros.
     *
     * @var array
     */
    protected $local_macros = [];
    /**
     * A replacement for the typical delete function.
     *
     * @var \Closure
     */
    protected $on_delete;
    /**
     * The properties that should be returned from query builder.
     *
     * @var string[]
     */
    protected $property_passthru = ['from'];
    /**
     * The methods that should be returned from query builder.
     *
     * @var string[]
     */
    protected $passthru = ['aggregate', 'average', 'avg', 'count', 'dd', 'ddrawsql', 'doesntexist', 'doesntexistor', 'dump', 'dumprawsql', 'exists', 'existsor', 'explain', 'getbindings', 'getconnection', 'getcountforpagination', 'getgrammar', 'getrawbindings', 'implode', 'insert', 'insertgetid', 'insertorignore', 'insertusing', 'insertorignoreusing', 'max', 'min', 'numericaggregate', 'raw', 'rawvalue', 'sum', 'tosql', 'torawsql'];
    /**
     * Applied global scopes.
     *
     * @var array
     */
    protected $scopes = [];
    /**
     * Removed global scopes.
     *
     * @var array
     */
    protected $removed_scopes = [];
    /**
     * The callbacks that should be invoked after retrieving data from the database.
     *
     * @var array
     */
    protected $after_query_callbacks = [];
    /**
     * The callbacks that should be invoked on clone.
     *
     * @var array
     */
    protected $on_clone_callbacks = [];
    /**
     * Create a new Eloquent query builder instance.
     */
    public function __construct(
        /**
         * The base query builder instance.
         */
        protected \Illuminate\Database\Query\Builder $query
    )
    {
    }
    /**
     * Create and return an un-saved model instance.
     *
     * @return TModel
     */
    public function make(array $attributes = [])
    {
        return $this->new_model_instance($attributes);
    }
    /**
     * Register a new global scope.
     *
     * @param  string  $identifier
     * @param  \Illuminate\Database\Eloquent\Scope|\Closure  $scope
     * @return $this
     */
    public function with_global_scope($identifier, $scope): static
    {
        $this->scopes[$identifier] = $scope;
        if (method_exists($scope, 'extend')) {
            $scope->extend($this);
        }
        return $this;
    }
    /**
     * Remove a registered global scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return $this
     */
    public function without_global_scope($scope): static
    {
        if (!is_string($scope)) {
            $scope = $scope::class;
        }
        unset($this->scopes[$scope]);
        $this->removed_scopes[] = $scope;
        return $this;
    }
    /**
     * Remove all or passed registered global scopes.
     *
     * @return $this
     */
    public function without_global_scopes(?array $scopes = null): static
    {
        if (!is_array($scopes)) {
            $scopes = array_keys($this->scopes);
        }
        foreach ($scopes as $scope) {
            $this->without_global_scope($scope);
        }
        return $this;
    }
    /**
     * Remove all global scopes except the given scopes.
     *
     * @return $this
     */
    public function without_global_scopes_except(array $scopes = []): static
    {
        $this->without_global_scopes(array_diff(array_keys($this->scopes), $scopes));
        return $this;
    }
    /**
     * Get an array of global scopes that were removed from the query.
     *
     * @return array
     */
    public function removed_scopes()
    {
        return $this->removed_scopes;
    }
    /**
     * Add a where clause on the primary key to the query.
     *
     * @param  mixed  $id
     * @return $this
     */
    public function where_key($id): static
    {
        if ($id instanceof Model) {
            $id = $id->get_key();
        }
        if (is_array($id) || $id instanceof Arrayable) {
            if (in_array($this->model->get_key_type(), ['int', 'integer'])) {
                $this->query->where_integer_in_raw($this->model->get_qualified_key_name(), $id);
            } else {
                $this->query->where_in($this->model->get_qualified_key_name(), $id);
            }
            return $this;
        }
        if ($id !== null && $this->model->get_key_type() === 'string') {
            $id = (string) $id;
        }
        return $this->where($this->model->get_qualified_key_name(), '=', $id);
    }
    /**
     * Add a where clause on the primary key to the query.
     *
     * @param  mixed  $id
     * @return $this
     */
    public function where_key_not($id): static
    {
        if ($id instanceof Model) {
            $id = $id->get_key();
        }
        if (is_array($id) || $id instanceof Arrayable) {
            if (in_array($this->model->get_key_type(), ['int', 'integer'])) {
                $this->query->where_integer_not_in_raw($this->model->get_qualified_key_name(), $id);
            } else {
                $this->query->where_not_in($this->model->get_qualified_key_name(), $id);
            }
            return $this;
        }
        if ($id !== null && $this->model->get_key_type() === 'string') {
            $id = (string) $id;
        }
        return $this->where($this->model->get_qualified_key_name(), '!=', $id);
    }
    /**
     * Exclude the given models from the query results.
     *
     * @param  iterable|mixed  $models
     * @return static
     */
    public function except($models)
    {
        return $this->where_key_not($models instanceof Model ? $models->get_key() : Collection::wrap($models)->model_keys());
    }
    /**
     * Add a basic where clause to the query.
     *
     * @param  (\Closure(static): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        if ($column instanceof Closure && is_null($operator)) {
            $column($query = $this->model->new_query_without_relationships());
            $this->eager_load = array_merge($this->eager_load, $query->get_eager_loads());
            $this->query->add_nested_where_query($query->get_query(), $boolean);
        } else {
            $this->query->where(...func_get_args());
        }
        return $this;
    }
    /**
     * Add a basic where clause to the query, and return the first result.
     *
     * @param  (\Closure(static): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return TModel|null
     */
    public function first_where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return $this->where(...func_get_args())->first();
    }
    /**
     * Add an "or where" clause to the query.
     *
     * @param  (\Closure(static): mixed)|array|string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where($column, $operator = null, $value = null): static
    {
        [$value, $operator] = $this->query->prepare_value_and_operator($value, $operator, func_num_args() === 2);
        return $this->where($column, $operator, $value, 'or');
    }
    /**
     * Add a basic "where not" clause to the query.
     *
     * @param  (\Closure(static): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function where_not($column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        return $this->where($column, $operator, $value, $boolean . ' not');
    }
    /**
     * Add an "or where not" clause to the query.
     *
     * @param  (\Closure(static): mixed)|array|string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_not($column, $operator = null, $value = null)
    {
        return $this->where_not($column, $operator, $value, 'or');
    }
    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return $this
     */
    public function latest($column = null): static
    {
        if (is_null($column)) {
            $column = $this->model->get_created_at_column() ?? 'created_at';
        }
        $this->query->latest($column);
        return $this;
    }
    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return $this
     */
    public function oldest($column = null): static
    {
        if (is_null($column)) {
            $column = $this->model->get_created_at_column() ?? 'created_at';
        }
        $this->query->oldest($column);
        return $this;
    }
    /**
     * Create a collection of models from plain arrays.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function hydrate(array $items): object
    {
        $instance = $this->new_model_instance();
        return $instance->new_collection(array_map(function ($item) use ($items, $instance) {
            $model = $instance->new_from_builder($item);
            if (count($items) > 1) {
                $model->prevents_lazy_loading = Model::prevents_lazy_loading();
            }
            return $model;
        }, $items));
    }
    /**
     * Insert into the database after merging the model's default attributes, setting timestamps, and casting values.
     *
     * @param  array<int, array<string, mixed>>  $values
     * @return bool
     */
    public function fill_and_insert(array $values)
    {
        return $this->insert($this->fill_for_insert($values));
    }
    /**
     * Insert (ignoring errors) into the database after merging the model's default attributes, setting timestamps, and casting values.
     *
     * @param  array<int, array<string, mixed>>  $values
     * @return int
     */
    public function fill_and_insert_or_ignore(array $values)
    {
        return $this->insert_or_ignore($this->fill_for_insert($values));
    }
    /**
     * Insert a record into the database and get its ID after merging the model's default attributes, setting timestamps, and casting values.
     *
     * @param  array<string, mixed>  $values
     * @return int
     */
    public function fill_and_insert_get_id(array $values)
    {
        return $this->insert_get_id($this->fill_for_insert([$values])[0]);
    }
    /**
     * Enrich the given values by merging in the model's default attributes, adding timestamps, and casting values.
     *
     * @param  array<int, array<string, mixed>>  $values
     * @return array<int, array<string, mixed>>
     */
    public function fill_for_insert(array $values): array
    {
        if (empty($values)) {
            return [];
        }
        if (!is_array(array_first($values))) {
            $values = [$values];
        }
        $this->model->unguarded(function () use (&$values): void {
            foreach ($values as $key => $row_values) {
                $values[$key] = tap($this->new_model_instance($row_values), fn($model) => $model->set_unique_ids())->get_attributes();
            }
        });
        return $this->add_timestamps_to_upsert_values($values);
    }
    /**
     * Create a collection of models from a raw query.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function from_query($query, $bindings = [])
    {
        return $this->hydrate($this->query->get_connection()->select($query, $bindings));
    }
    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array|string  $columns
     * @return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? \Illuminate\Database\Eloquent\Collection<int, TModel> : TModel|null)
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->find_many($id, $columns);
        }
        return $this->where_key($id)->first($columns);
    }
    /**
     * Find a sole model by its primary key.
     *
     * @param  mixed  $id
     * @param  array|string  $columns
     * @return TModel
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function find_sole($id, $columns = ['*'])
    {
        return $this->where_key($id)->sole($columns);
    }
    /**
     * Find multiple models by their primary keys.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $ids
     * @param  array|string  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function find_many($ids, $columns = ['*'])
    {
        $ids = $ids instanceof Arrayable ? $ids->to_array() : $ids;
        if (empty($ids)) {
            return $this->model->new_collection();
        }
        return $this->where_key($ids)->get($columns);
    }
    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array|string  $columns
     * @return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? \Illuminate\Database\Eloquent\Collection<int, TModel> : TModel)
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
     */
    public function find_or_fail($id, $columns = ['*'])
    {
        $result = $this->find($id, $columns);
        $id = $id instanceof Arrayable ? $id->to_array() : $id;
        if (is_array($id)) {
            if (count($result) !== count(array_unique($id))) {
                throw (new Model_Not_Found_Exception())->set_model($this->model::class, array_diff($id, $result->model_keys()));
            }
            return $result;
        }
        if (is_null($result)) {
            throw (new Model_Not_Found_Exception())->set_model($this->model::class, $id);
        }
        return $result;
    }
    /**
     * Find a model by its primary key or return fresh model instance.
     *
     * @param  mixed  $id
     * @param  array|string  $columns
     * @return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? \Illuminate\Database\Eloquent\Collection<int, TModel> : TModel)
     */
    public function find_or_new($id, $columns = ['*'])
    {
        if (!is_null($model = $this->find($id, $columns))) {
            return $model;
        }
        return $this->new_model_instance();
    }
    /**
     * Find a model by its primary key or call a callback.
     *
     * @template TValue
     *
     * @param  mixed  $id
     * @param  (\Closure(): TValue)|list<string>|string  $columns
     * @param  (\Closure(): TValue)|null  $callback
     * @return (
     *     $id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>)
     *     ? \Illuminate\Database\Eloquent\Collection<int, TModel>
     *     : TModel|TValue
     * )
     */
    public function find_or($id, $columns = ['*'], ?Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            $callback = $columns;
            $columns = ['*'];
        }
        if (!is_null($model = $this->find($id, $columns))) {
            return $model;
        }
        return $callback();
    }
    /**
     * Get the first record matching the attributes or instantiate it.
     *
     * @return TModel
     */
    public function first_or_new(array $attributes = [], array $values = [])
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }
        return $this->new_model_instance(array_merge($attributes, $values));
    }
    /**
     * Get the first record matching the attributes. If the record is not found, create it.
     *
     * @param  (\Closure(): array)|array  $values
     * @return TModel
     */
    public function first_or_create(array $attributes = [], Closure|array $values = [])
    {
        if (!is_null($instance = (clone $this)->where($attributes)->first())) {
            return $instance;
        }
        return $this->create_or_first($attributes, $values);
    }
    /**
     * Attempt to create the record. If a unique constraint violation occurs, attempt to find the matching record.
     *
     * @param  (\Closure(): array)|array  $values
     * @return TModel
     */
    public function create_or_first(array $attributes = [], Closure|array $values = [])
    {
        try {
            return $this->with_savepoint_if_needed(fn() => $this->create(array_merge($attributes, value($values))));
        } catch (Unique_Constraint_Violation_Exception $e) {
            return $this->use_write_pdo()->where($attributes)->first() ?? throw $e;
        }
    }
    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @return TModel
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
     * Create a record matching the attributes, or increment the existing record.
     *
     * @param  int|float  $default
     * @param  int|float  $step
     * @return TModel
     */
    public function increment_or_create(array $attributes, string $column = 'count', $default = 1, $step = 1, array $extra = [])
    {
        return tap($this->first_or_create($attributes, [$column => $default]), function ($instance) use ($column, $step, $extra): void {
            if (!$instance->was_recently_created) {
                $instance->increment($column, $step, $extra);
            }
        });
    }
    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array|string  $columns
     * @return TModel
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
     */
    public function first_or_fail($columns = ['*'])
    {
        if (!is_null($model = $this->first($columns))) {
            return $model;
        }
        throw (new Model_Not_Found_Exception())->set_model($this->model::class);
    }
    /**
     * Execute the query and get the first result or call a callback.
     *
     * @template TValue
     *
     * @param  (\Closure(): TValue)|list<string>  $columns
     * @param  (\Closure(): TValue)|null  $callback
     * @return TModel|TValue
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
     * Execute the query and get the first result if it's the sole matching record.
     *
     * @param  array|string  $columns
     * @return TModel
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function sole($columns = ['*'])
    {
        try {
            return $this->base_sole($columns);
        } catch (Records_Not_Found_Exception) {
            throw (new Model_Not_Found_Exception())->set_model($this->model::class);
        }
    }
    /**
     * Get a single column's value from the first result of a query.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return mixed
     */
    public function value($column)
    {
        if ($result = $this->first([$column])) {
            $column = $column instanceof Expression ? $column->get_value($this->get_grammar()) : $column;
            return $result->{Str::after_last($column, '.')};
        }
    }
    /**
     * Get a single column's value from the first result of a query if it's the sole matching record.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return mixed
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function sole_value($column)
    {
        $column = $column instanceof Expression ? $column->get_value($this->get_grammar()) : $column;
        return $this->sole([$column])->{Str::after_last($column, '.')};
    }
    /**
     * Get a single column's value from the first result of the query or throw an exception.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return mixed
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TModel>
     */
    public function value_or_fail($column)
    {
        $column = $column instanceof Expression ? $column->get_value($this->get_grammar()) : $column;
        return $this->first_or_fail([$column])->{Str::after_last($column, '.')};
    }
    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function get($columns = ['*'])
    {
        $builder = $this->apply_scopes();
        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models = $builder->get_models($columns)) > 0) {
            $models = $builder->eager_load_relations($models);
        }
        return $this->apply_after_query_callbacks($builder->get_model()->new_collection($models));
    }
    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array|string  $columns
     * @return array<int, TModel>
     */
    public function get_models($columns = ['*'])
    {
        return $this->model->hydrate($this->query->get($columns)->all())->all();
    }
    /**
     * Eager load the relationships for the models.
     *
     * @param  array<int, TModel>  $models
     * @return array<int, TModel>
     */
    public function eager_load_relations(array $models)
    {
        foreach ($this->eager_load as $name => $constraints) {
            // For nested eager loads we'll skip loading them here and they will be set as an
            // eager load on the query to retrieve the relation so that they will be eager
            // loaded on that query, because that is where they get hydrated as models.
            if (!str_contains((string) $name, '.')) {
                $models = $this->eager_load_relation($models, $name, $constraints);
            }
        }
        return $models;
    }
    /**
     * Eagerly load the relationship on a set of models.
     *
     * @param  string  $name
     * @return array
     */
    protected function eager_load_relation(array $models, $name, Closure $constraints)
    {
        // First we will "back up" the existing where conditions on the query so we can
        // add our eager constraints. Then we will merge the wheres that were on the
        // query back to it in order that any where conditions might be specified.
        $relation = $this->get_relation($name);
        $relation->add_eager_constraints($models);
        $constraints($relation);
        // Once we have the results, we just match those back up to their parent models
        // using the relationship instance. Then we just return the finished arrays
        // of models which have been eagerly hydrated and are readied for return.
        return $relation->match($relation->init_relation($models, $name), $relation->get_eager(), $name);
    }
    /**
     * Get the relation instance for the given relation name.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model, TModel, *>
     */
    public function get_relation(string $name)
    {
        // We want to run a relationship query without any constrains so that we will
        // not have to remove these where clauses manually which gets really hacky
        // and error prone. We don't want constraints because we add eager ones.
        $relation = Relation::no_constraints(function () use ($name) {
            try {
                return $this->get_model()->new_instance()->{$name}();
            } catch (BadMethodCallException) {
                throw Relation_Not_Found_Exception::make($this->get_model(), $name);
            }
        });
        $nested = $this->relations_nested_under($name);
        // If there are nested relationships set on the query, we will put those onto
        // the query instances so that they can be handled after this relationship
        // is loaded. In this way they will all trickle down as they are loaded.
        if (count($nested) > 0) {
            $relation->get_query()->with($nested);
        }
        return $relation;
    }
    /**
     * Get the deeply nested relations for a given top-level relation.
     */
    protected function relations_nested_under(string $relation): array
    {
        $nested = [];
        // We are basically looking for any relationships that are nested deeper than
        // the given top-level relationship. We will just check for any relations
        // that start with the given top relations and adds them to our arrays.
        foreach ($this->eager_load as $name => $constraints) {
            if ($this->is_nested_under($relation, $name)) {
                $nested[substr((string) $name, strlen($relation . '.'))] = $constraints;
            }
        }
        return $nested;
    }
    /**
     * Determine if the relationship is nested.
     *
     * @param  string  $name
     */
    protected function is_nested_under(string $relation, $name): bool
    {
        return str_contains($name, '.') && str_starts_with($name, $relation . '.');
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
     * Get a lazy collection for the given query.
     *
     * @return \Illuminate\Support\LazyCollection<int, TModel>
     */
    public function cursor()
    {
        return $this->apply_scopes()->query->cursor()->map(function ($record) {
            $model = $this->new_model_instance()->new_from_builder($record);
            return $this->apply_after_query_callbacks($this->new_model_instance()->new_collection([$model]))->first();
        })->reject(fn($model): bool => is_null($model));
    }
    /**
     * Add a generic "order by" clause if the query doesn't already have one.
     *
     * @return void
     */
    protected function enforce_order_by()
    {
        if (empty($this->query->orders) && empty($this->query->union_orders)) {
            $this->order_by($this->model->get_qualified_key_name(), 'asc');
        }
    }
    /**
     * Get a collection with the values of a given column.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    public function pluck($column, $key = null)
    {
        $results = $this->to_base()->pluck($column, $key);
        $column = $column instanceof Expression ? $column->get_value($this->get_grammar()) : $column;
        $column = Str::after($column, "{$this->model->get_table()}.");
        // If the model has a mutator for the requested column, we will spin through
        // the results and mutate the values so that the mutated version of these
        // columns are returned as you would expect from these Eloquent models.
        if (!$this->model->has_any_get_mutator($column) && !$this->model->has_cast($column) && !in_array($column, $this->model->get_dates())) {
            return $this->apply_after_query_callbacks($results);
        }
        return $this->apply_after_query_callbacks($results->map(fn($value) => $this->model->new_from_builder([$column => $value])->{$column}));
    }
    /**
     * Paginate the given query.
     *
     * @param  int|null|\Closure  $perPage
     * @param  array|string  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @param  \Closure|int|null  $total
     * @return \Illuminate\Pagination\LengthAwarePaginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($per_page = null, $columns = ['*'], $page_name = 'page', $page = null, $total = null)
    {
        $page = $page ?: Paginator::resolve_current_page($page_name);
        $total = value($total) ?? $this->to_base()->get_count_for_pagination();
        $per_page = value($per_page, $total) ?: $this->model->get_per_page();
        $results = $total ? $this->for_page($page, $per_page)->get($columns) : $this->model->new_collection();
        return $this->paginator($results, $total, $per_page, $page, ['path' => Paginator::resolve_current_path(), 'pageName' => $page_name]);
    }
    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int|null  $perPage
     * @param  array|string  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simple_paginate($per_page = null, $columns = ['*'], $page_name = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolve_current_page($page_name);
        $per_page = $per_page ?: $this->model->get_per_page();
        // Next we will set the limit and offset for this query so that when we get the
        // results we get the proper section of results. Then, we'll create the full
        // paginator instances for these results with the given page and per page.
        $this->offset(($page - 1) * $per_page)->limit($per_page + 1);
        return $this->simple_paginator($this->get($columns), $per_page, $page, ['path' => Paginator::resolve_current_path(), 'pageName' => $page_name]);
    }
    /**
     * Paginate the given query into a cursor paginator.
     *
     * @param  int|null  $perPage
     * @param  array|string  $columns
     * @param  string  $cursorName
     * @param  \Illuminate\Pagination\Cursor|string|null  $cursor
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    public function cursor_paginate($per_page = null, $columns = ['*'], $cursor_name = 'cursor', $cursor = null)
    {
        $per_page = $per_page ?: $this->model->get_per_page();
        return $this->paginate_using_cursor($per_page, $columns, $cursor_name, $cursor);
    }
    /**
     * Ensure the proper order by required for cursor pagination.
     *
     * @param  bool  $shouldReverse
     */
    protected function ensure_order_for_cursor_pagination($should_reverse = false): \Illuminate\Support\Collection
    {
        if (empty($this->query->orders) && empty($this->query->union_orders)) {
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
            $this->query->orders = (new Base_Collection($this->query->orders))->map($reverse_direction)->to_array();
            $this->query->union_orders = (new Base_Collection($this->query->union_orders))->map($reverse_direction)->to_array();
        }
        $orders = !empty($this->query->union_orders) ? $this->query->union_orders : $this->query->orders;
        return (new Base_Collection($orders))->filter(fn($order): bool => Arr::has($order, 'direction'))->values();
    }
    /**
     * Save a new model and return the instance.
     *
     * @return TModel
     */
    public function create(array $attributes = [])
    {
        return tap($this->new_model_instance($attributes), function ($instance): void {
            $instance->save();
        });
    }
    /**
     * Save a new model and return the instance without raising model events.
     *
     * @return TModel
     */
    public function create_quietly(array $attributes = [])
    {
        return Model::without_events(fn() => $this->create($attributes));
    }
    /**
     * Save a new model and return the instance. Allow mass-assignment.
     *
     * @return TModel
     */
    public function force_create(array $attributes)
    {
        return $this->model->unguarded(fn() => $this->new_model_instance()->create($attributes));
    }
    /**
     * Save a new model instance with mass assignment without raising model events.
     *
     * @return TModel
     */
    public function force_create_quietly(array $attributes = [])
    {
        return Model::without_events(fn() => $this->force_create($attributes));
    }
    /**
     * Update records in the database.
     *
     * @return int
     */
    public function update(array $values)
    {
        return $this->to_base()->update($this->add_updated_at_column($values));
    }
    /**
     * Insert new records or update the existing ones.
     *
     * @param  array|null  $update
     * @return int
     */
    public function upsert(array $values, array|string $unique_by, $update = null)
    {
        if (empty($values)) {
            return 0;
        }
        if (!is_array(array_first($values))) {
            $values = [$values];
        }
        if (is_null($update)) {
            $update = array_keys(array_first($values));
        }
        return $this->to_base()->upsert($this->add_timestamps_to_upsert_values($this->add_unique_ids_to_upsert_values($values)), $unique_by, $this->add_updated_at_to_upsert_columns($update));
    }
    /**
     * Update the column's update timestamp.
     *
     * @param  string|null  $column
     * @return int|false
     */
    public function touch($column = null)
    {
        $time = $this->model->fresh_timestamp();
        if ($column) {
            return $this->to_base()->update([$column => $time]);
        }
        $column = $this->model->get_updated_at_column();
        if (!$this->model->uses_timestamps() || is_null($column)) {
            return false;
        }
        return $this->to_base()->update([$column => $time]);
    }
    /**
     * Increment a column's value by a given amount.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  float|int  $amount
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        return $this->to_base()->increment($column, $amount, $this->add_updated_at_column($extra));
    }
    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  float|int  $amount
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->to_base()->decrement($column, $amount, $this->add_updated_at_column($extra));
    }
    /**
     * Add the "updated at" column to an array of values.
     *
     * @return array
     */
    protected function add_updated_at_column(array $values)
    {
        if (!$this->model->uses_timestamps() || is_null($this->model->get_updated_at_column())) {
            return $values;
        }
        $column = $this->model->get_updated_at_column();
        if (!array_key_exists($column, $values)) {
            $timestamp = $this->model->fresh_timestamp_string();
            if ($this->model->has_set_mutator($column) || $this->model->has_attribute_set_mutator($column) || $this->model->has_cast($column)) {
                $timestamp = $this->model->new_instance()->force_fill([$column => $timestamp])->get_attributes()[$column] ?? $timestamp;
            }
            $values = array_merge([$column => $timestamp], $values);
        }
        $segments = preg_split('/\s+as\s+/i', $this->query->from);
        $qualified_column = array_last($segments) . '.' . $column;
        $values[$qualified_column] = Arr::get($values, $qualified_column, $values[$column]);
        unset($values[$column]);
        return $values;
    }
    /**
     * Add unique IDs to the inserted values.
     */
    protected function add_unique_ids_to_upsert_values(array $values): array
    {
        if (!$this->model->uses_unique_ids()) {
            return $values;
        }
        foreach ($this->model->unique_ids() as $unique_id_attribute) {
            foreach ($values as &$row) {
                if (!array_key_exists($unique_id_attribute, $row)) {
                    $row = array_merge([$unique_id_attribute => $this->model->new_unique_id()], $row);
                }
            }
        }
        return $values;
    }
    /**
     * Add timestamps to the inserted values.
     */
    protected function add_timestamps_to_upsert_values(array $values): array
    {
        if (!$this->model->uses_timestamps()) {
            return $values;
        }
        $timestamp = $this->model->fresh_timestamp_string();
        $columns = array_filter([$this->model->get_created_at_column(), $this->model->get_updated_at_column()]);
        foreach ($columns as $column) {
            foreach ($values as &$row) {
                $row = array_merge([$column => $timestamp], $row);
            }
        }
        return $values;
    }
    /**
     * Add the "updated at" column to the updated columns.
     */
    protected function add_updated_at_to_upsert_columns(array $update): array
    {
        if (!$this->model->uses_timestamps()) {
            return $update;
        }
        $column = $this->model->get_updated_at_column();
        if (!is_null($column) && !array_key_exists($column, $update) && !in_array($column, $update)) {
            $update[] = $column;
        }
        return $update;
    }
    /**
     * Delete records from the database.
     *
     * @return mixed
     */
    public function delete()
    {
        if (isset($this->on_delete)) {
            return call_user_func($this->on_delete, $this);
        }
        return $this->to_base()->delete();
    }
    /**
     * Run the default delete function on the builder.
     *
     * Since we do not apply scopes here, the row will actually be deleted.
     *
     * @return mixed
     */
    public function force_delete()
    {
        return $this->query->delete();
    }
    /**
     * Register a replacement for the default delete function.
     */
    public function on_delete(Closure $callback): void
    {
        $this->on_delete = $callback;
    }
    /**
     * Determine if the given model has a scope.
     *
     * @param  string  $scope
     */
    public function has_named_scope($scope): bool
    {
        return $this->model && $this->model->has_named_scope($scope);
    }
    /**
     * Call the given local model scopes.
     *
     * @param  array|string  $scopes
     * @return static|mixed
     */
    public function scopes($scopes)
    {
        $builder = $this;
        foreach (Arr::wrap($scopes) as $scope => $parameters) {
            // If the scope key is an integer, then the scope was passed as the value and
            // the parameter list is empty, so we will format the scope name and these
            // parameters here. Then, we'll be ready to call the scope on the model.
            if (is_int($scope)) {
                [$scope, $parameters] = [$parameters, []];
            }
            // Next we'll pass the scope callback to the callScope method which will take
            // care of grouping the "wheres" properly so the logical order doesn't get
            // messed up when adding scopes. Then we'll return back out the builder.
            $builder = $builder->call_named_scope($scope, Arr::wrap($parameters));
        }
        return $builder;
    }
    /**
     * Apply the scopes to the Eloquent builder instance and return it.
     */
    public function apply_scopes(): static
    {
        if (!$this->scopes) {
            return $this;
        }
        $builder = clone $this;
        foreach ($this->scopes as $identifier => $scope) {
            if (!isset($builder->scopes[$identifier])) {
                continue;
            }
            $builder->call_scope(function (self $builder) use ($scope): void {
                // If the scope is a Closure we will just go ahead and call the scope with the
                // builder instance. The "callScope" method will properly group the clauses
                // that are added to this query so "where" clauses maintain proper logic.
                if ($scope instanceof Closure) {
                    $scope($builder);
                }
                // If the scope is a scope object, we will call the apply method on this scope
                // passing in the builder and the model instance. After we run all of these
                // scopes we will return back the builder instance to the outside caller.
                if ($scope instanceof Scope) {
                    $scope->apply($builder, $this->get_model());
                }
            });
        }
        return $builder;
    }
    /**
     * Apply the given scope on the current builder instance.
     *
     * @return mixed
     */
    protected function call_scope(callable $scope, array $parameters = [])
    {
        array_unshift($parameters, $this);
        $query = $this->get_query();
        // We will keep track of how many wheres are on the query before running the
        // scope so that we can properly group the added scope constraints in the
        // query as their own isolated nested where statement and avoid issues.
        $original_where_count = is_null($query->wheres) ? 0 : count($query->wheres);
        $result = $scope(...$parameters) ?? $this;
        if (count((array) $query->wheres) > $original_where_count) {
            $this->add_new_wheres_within_group($query, $original_where_count);
        }
        return $result;
    }
    /**
     * Apply the given named scope on the current builder instance.
     *
     * @param  string  $scope
     * @return mixed
     */
    protected function call_named_scope($scope, array $parameters = [])
    {
        return $this->call_scope(fn(...$parameters) => $this->model->call_named_scope($scope, $parameters), $parameters);
    }
    /**
     * Nest where conditions by slicing them at the given where count.
     *
     * @param  int  $originalWhereCount
     * @return void
     */
    protected function add_new_wheres_within_group(Query_Builder $query, $original_where_count)
    {
        // Here, we totally remove all of the where clauses since we are going to
        // rebuild them as nested queries by slicing the groups of wheres into
        // their own sections. This is to prevent any confusing logic order.
        $all_wheres = $query->wheres;
        $query->wheres = [];
        $this->group_where_slice_for_scope($query, array_slice($all_wheres, 0, $original_where_count));
        $this->group_where_slice_for_scope($query, array_slice($all_wheres, $original_where_count));
    }
    /**
     * Slice where conditions at the given offset and add them to the query as a nested condition.
     *
     * @param  array  $whereSlice
     * @return void
     */
    protected function group_where_slice_for_scope(Query_Builder $query, $where_slice)
    {
        $where_booleans = (new Base_Collection($where_slice))->pluck('boolean');
        // Here we'll check if the given subset of where clauses contains any "or"
        // booleans and in this case create a nested where expression. That way
        // we don't add any unnecessary nesting thus keeping the query clean.
        if ($where_booleans->contains(fn($logical_operator): bool => str_contains((string) $logical_operator, 'or'))) {
            $query->wheres[] = $this->create_nested_where($where_slice, str_replace(' not', '', $where_booleans->first()));
        } else {
            $query->wheres = array_merge($query->wheres, $where_slice);
        }
    }
    /**
     * Create a where array with nested where conditions.
     *
     * @param  array  $whereSlice
     * @param  string  $boolean
     */
    protected function create_nested_where($where_slice, $boolean = 'and'): array
    {
        $where_group = $this->get_query()->for_nested_where();
        $where_group->wheres = $where_slice;
        return ['type' => 'Nested', 'query' => $where_group, 'boolean' => $boolean];
    }
    /**
     * Specify relationships that should be eager loaded.
     *
     * @param  array<array-key, array|(\Closure(\Illuminate\Database\Eloquent\Relations\Relation<*,*,*>): mixed)|string>|string  $relations
     * @param  (\Closure(\Illuminate\Database\Eloquent\Relations\Relation<*,*,*>): mixed)|string|null  $callback
     * @return $this
     */
    public function with($relations, $callback = null): static
    {
        if ($callback instanceof Closure) {
            $eager_load = $this->parse_with_relations([$relations => $callback]);
        } else {
            $eager_load = $this->parse_with_relations(is_string($relations) ? func_get_args() : $relations);
        }
        $this->eager_load = array_merge($this->eager_load, $eager_load);
        return $this;
    }
    /**
     * Prevent the specified relations from being eager loaded.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function without($relations): static
    {
        $this->eager_load = array_diff_key($this->eager_load, array_flip(is_string($relations) ? func_get_args() : $relations));
        return $this;
    }
    /**
     * Set the relationships that should be eager loaded while removing any previously added eager loading specifications.
     *
     * @param  array<array-key, array|(\Closure(\Illuminate\Database\Eloquent\Relations\Relation<*,*,*>): mixed)|string>|string  $relations
     * @return $this
     */
    public function with_only($relations): static
    {
        $this->eager_load = [];
        return $this->with($relations);
    }
    /**
     * Create a new instance of the model being queried.
     *
     * @param  array  $attributes
     * @return TModel
     */
    public function new_model_instance($attributes = [])
    {
        $attributes = array_merge($this->pending_attributes, $attributes);
        return $this->model->new_instance($attributes)->set_connection($this->query->get_connection()->get_name());
    }
    /**
     * Parse a list of relations into individuals.
     */
    protected function parse_with_relations(array $relations): array
    {
        if ($relations === []) {
            return [];
        }
        $results = [];
        foreach ($this->prepare_nested_with_relationships($relations) as $name => $constraints) {
            // We need to separate out any nested includes, which allows the developers
            // to load deep relationships using "dots" without stating each level of
            // the relationship with its own key in the array of eager-load names.
            $results = $this->add_nested_withs($name, $results);
            $results[$name] = $constraints;
        }
        return $results;
    }
    /**
     * Prepare nested with relationships.
     *
     * @param  string  $prefix
     */
    protected function prepare_nested_with_relationships(array $relations, $prefix = ''): array
    {
        $prepared_relationships = [];
        if ($prefix !== '') {
            $prefix .= '.';
        }
        // If any of the relationships are formatted with the [$attribute => array()]
        // syntax, we shall loop over the nested relations and prepend each key of
        // this array while flattening into the traditional dot notation format.
        foreach ($relations as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (!is_array($value)) {
                continue;
            }
            [$attribute, $attribute_select_constraint] = $this->parse_name_and_attribute_selection_constraint($key);
            $prepared_relationships = array_merge($prepared_relationships, ["{$prefix}{$attribute}" => $attribute_select_constraint], $this->prepare_nested_with_relationships($value, "{$prefix}{$attribute}"));
            unset($relations[$key]);
        }
        // We now know that the remaining relationships are in a dot notation format
        // and may be a string or Closure. We'll loop over them and ensure all of
        // the present Closures are merged + strings are made into constraints.
        foreach ($relations as $key => $value) {
            if (is_numeric($key) && is_string($value)) {
                [$key, $value] = $this->parse_name_and_attribute_selection_constraint($value);
            }
            $prepared_relationships[$prefix . $key] = $this->combine_constraints([$value, $prepared_relationships[$prefix . $key] ?? static function (): void {
            }]);
        }
        return $prepared_relationships;
    }
    /**
     * Combine an array of constraints into a single constraint.
     *
     * @return \Closure
     */
    protected function combine_constraints(array $constraints)
    {
        return function ($builder) use ($constraints) {
            foreach ($constraints as $constraint) {
                $builder = $constraint($builder) ?? $builder;
            }
            return $builder;
        };
    }
    /**
     * Parse the attribute select constraints from the name.
     *
     * @param  string  $name
     */
    protected function parse_name_and_attribute_selection_constraint($name): array
    {
        return str_contains($name, ':') ? $this->create_select_with_constraint($name) : [$name, static function (): void {
        }];
    }
    /**
     * Create a constraint to select the given columns for the relation.
     *
     * @param  string  $name
     */
    protected function create_select_with_constraint($name): array
    {
        return [explode(':', $name)[0], static function ($query) use ($name): void {
            $query->select(array_map(static fn($column) => $query instanceof Belongs_To_Many ? $query->get_related()->qualify_column($column) : $column, explode(',', explode(':', $name)[1])));
        }];
    }
    /**
     * Parse the nested relationships in a relation.
     *
     * @param  string  $name
     */
    protected function add_nested_withs($name, array $results): array
    {
        $progress = [];
        // If the relation has already been set on the result array, we will not set it
        // again, since that would override any constraints that were already placed
        // on the relationships. We will only set the ones that are not specified.
        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;
            if (!isset($results[$last = implode('.', $progress)])) {
                $results[$last] = static function (): void {
                };
            }
        }
        return $results;
    }
    /**
     * Specify attributes that should be added to any new models created by this builder.
     *
     * The given key / value pairs will also be added as where conditions to the query.
     *
     * @param  mixed  $value
     * @param  bool  $asConditions
     * @return $this
     */
    public function with_attributes(Expression|array|string $attributes, $value = null, $as_conditions = true): static
    {
        if (!is_array($attributes)) {
            $attributes = [$attributes => $value];
        }
        if ($as_conditions) {
            foreach ($attributes as $column => $value) {
                $this->where($this->qualify_column($column), $value);
            }
        }
        $this->pending_attributes = array_merge($this->pending_attributes, $attributes);
        return $this;
    }
    /**
     * Apply query-time casts to the model instance.
     *
     * @param  array  $casts
     * @return $this
     */
    public function with_casts($casts): static
    {
        $this->model->merge_casts($casts);
        return $this;
    }
    /**
     * Execute the given Closure within a transaction savepoint if needed.
     *
     * @template TModelValue
     *
     * @param  \Closure(): TModelValue  $scope
     * @return TModelValue
     */
    public function with_savepoint_if_needed(Closure $scope): mixed
    {
        return $this->get_query()->get_connection()->transaction_level() > 0 ? $this->get_query()->get_connection()->transaction($scope) : $scope();
    }
    /**
     * Get the Eloquent builder instances that are used in the union of the query.
     */
    protected function get_union_builders(): \Illuminate\Support\Collection
    {
        return isset($this->query->unions) ? (new Base_Collection($this->query->unions))->pluck('query') : new Base_Collection();
    }
    /**
     * Get the underlying query builder instance.
     */
    public function get_query(): \Illuminate\Database\Query\Builder
    {
        return $this->query;
    }
    /**
     * Set the underlying query builder instance.
     *
     * @return $this
     */
    public function set_query(\Illuminate\Database\Query\Builder $query): static
    {
        $this->query = $query;
        return $this;
    }
    /**
     * Get a base query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function to_base()
    {
        return $this->apply_scopes()->get_query();
    }
    /**
     * Get the relationships being eagerly loaded.
     *
     * @return array
     */
    public function get_eager_loads()
    {
        return $this->eager_load;
    }
    /**
     * Set the relationships being eagerly loaded.
     *
     * @return $this
     */
    public function set_eager_loads(array $eager_load): static
    {
        $this->eager_load = $eager_load;
        return $this;
    }
    /**
     * Indicate that the given relationships should not be eagerly loaded.
     *
     * @return $this
     */
    public function without_eager_load(array $relations): static
    {
        $relations = array_diff(array_keys($this->model->get_relations()), $relations);
        return $this->with($relations);
    }
    /**
     * Flush the relationships being eagerly loaded.
     *
     * @return $this
     */
    public function without_eager_loads(): static
    {
        return $this->set_eager_loads([]);
    }
    /**
     * Get the "limit" value from the query or null if it's not set.
     */
    public function get_limit(): ?int
    {
        return $this->query->get_limit();
    }
    /**
     * Get the "offset" value from the query or null if it's not set.
     */
    public function get_offset(): ?int
    {
        return $this->query->get_offset();
    }
    /**
     * Get the default key name of the table.
     *
     * @return string
     */
    protected function default_key_name()
    {
        return $this->get_model()->get_key_name();
    }
    /**
     * Get the model instance being queried.
     *
     * @return TModel
     */
    public function get_model()
    {
        return $this->model;
    }
    /**
     * Set a model instance for the model being queried.
     *
     * @template TModelNew of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModelNew  $model
     * @return static<TModelNew>
     */
    public function set_model(Model $model): static
    {
        $this->model = $model;
        $this->query->from($model->get_table());
        return $this;
    }
    /**
     * Qualify the given column name by the model's table.
     *
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @return string
     */
    public function qualify_column($column)
    {
        $column = $column instanceof Expression ? $column->get_value($this->get_grammar()) : $column;
        return $this->model->qualify_column($column);
    }
    /**
     * Qualify the given columns with the model's table.
     *
     * @param  array|\Illuminate\Contracts\Database\Query\Expression  $columns
     * @return array
     */
    public function qualify_columns($columns)
    {
        return $this->model->qualify_columns($columns);
    }
    /**
     * Get the given macro by name.
     *
     * @param  string  $name
     * @return \Closure
     */
    public function get_macro($name)
    {
        return Arr::get($this->local_macros, $name);
    }
    /**
     * Checks if a macro is registered.
     *
     * @param  string  $name
     */
    public function has_macro($name): bool
    {
        return isset($this->local_macros[$name]);
    }
    /**
     * Get the given global macro by name.
     *
     * @param  string  $name
     * @return \Closure
     */
    public static function get_global_macro($name)
    {
        return Arr::get(static::$macros, $name);
    }
    /**
     * Checks if a global macro is registered.
     *
     * @param  string  $name
     */
    public static function has_global_macro($name): bool
    {
        return isset(static::$macros[$name]);
    }
    /**
     * Dynamically access builder proxies.
     *
     *
     * @throws \Exception
     */
    public function __get(string $key): mixed
    {
        if (in_array($key, ['orWhere', 'whereNot', 'orWhereNot'])) {
            return new Higher_Order_Builder_Proxy($this, $key);
        }
        if (in_array($key, $this->property_passthru)) {
            return $this->to_base()->{$key};
        }
        throw new Exception("Property [{$key}] does not exist on the Eloquent builder instance.");
    }
    /**
     * Dynamically handle calls into the query instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if ($method === 'macro') {
            $this->local_macros[$parameters[0]] = $parameters[1];
            return;
        }
        if ($this->has_macro($method)) {
            array_unshift($parameters, $this);
            return $this->local_macros[$method](...$parameters);
        }
        if (static::has_global_macro($method)) {
            $callable = static::$macros[$method];
            if ($callable instanceof Closure) {
                $callable = $callable->bind_to($this, static::class);
            }
            return $callable(...$parameters);
        }
        if ($this->has_named_scope($method)) {
            return $this->call_named_scope($method, $parameters);
        }
        if (in_array(strtolower($method), $this->passthru)) {
            return $this->to_base()->{$method}(...$parameters);
        }
        $this->forward_call_to($this->query, $method, $parameters);
        return $this;
    }
    /**
     * Dynamically handle calls into the query instance.
     *
     * @return mixed
     * @throws \BadMethodCallException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if ($method === 'macro') {
            static::$macros[$parameters[0]] = $parameters[1];
            return;
        }
        if ($method === 'mixin') {
            return static::register_mixin($parameters[0], $parameters[1] ?? true);
        }
        if (!static::has_global_macro($method)) {
            static::throw_bad_method_call_exception($method);
        }
        $callable = static::$macros[$method];
        if ($callable instanceof Closure) {
            $callable = $callable->bind_to(null, static::class);
        }
        return $callable(...$parameters);
    }
    /**
     * Register the given mixin with the builder.
     *
     * @param  string  $mixin
     * @param  bool  $replace
     * @return void
     */
    protected static function register_mixin($mixin, $replace)
    {
        $methods = (new ReflectionClass($mixin))->get_methods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);
        foreach ($methods as $method) {
            if ($replace || !static::has_global_macro($method->name)) {
                static::macro($method->name, $method->invoke($mixin));
            }
        }
    }
    /**
     * Clone the Eloquent query builder.
     */
    public function clone(): static
    {
        return clone $this;
    }
    /**
     * Register a closure to be invoked on a clone.
     *
     * @return $this
     */
    public function on_clone(Closure $callback): static
    {
        $this->on_clone_callbacks[] = $callback;
        return $this;
    }
    /**
     * Force a clone of the underlying query builder when cloning.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
        foreach ($this->on_clone_callbacks as $on_clone_callback) {
            $on_clone_callback($this);
        }
    }
}