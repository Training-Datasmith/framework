<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model_Not_Found_Exception;
use Illuminate\Database\Multiple_Records_Found_Exception;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Traits\Forwards_Calls;
use Illuminate\Support\Traits\Macroable;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TResult
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<TRelatedModel>
 */
abstract class Relation implements Builder_Contract
{
    use Forwards_Calls, Macroable {
        Macroable::__call as macroCall;
    }
    /**
     * The Eloquent query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    protected $query;
    /**
     * The related model instance.
     *
     * @var TRelatedModel
     */
    protected $related;
    /**
     * Indicates whether the eagerly loaded relation should implicitly return an empty collection.
     *
     * @var bool
     */
    protected $eager_keys_were_empty = false;
    /**
     * Indicates if the relation is adding constraints.
     *
     * @var bool
     */
    protected static $constraints = true;
    /**
     * An array to map morph names to their class names in the database.
     *
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    public static $morph_map = [];
    /**
     * Prevents morph relationships without a morph map.
     *
     * @var bool
     */
    protected static $require_morph_map = false;
    /**
     * The count of self joins.
     *
     * @var int
     */
    protected static $self_join_count = 0;
    /**
     * Create a new relation instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     */
    public function __construct(
        Builder $query,
        /**
         * The parent model instance.
         */
        protected \Illuminate\Database\Eloquent\Model $parent
    )
    {
        $this->query = $query;
        $this->related = $query->get_model();
        $this->add_constraints();
    }
    /**
     * Run a callback with constraints disabled on the relation.
     *
     * The static `$constraints` flag is temporarily set to `false` while the
     * callback executes, preventing {@see addConstraints()} from adding its
     * usual WHERE clauses. This is used internally during eager loading to
     * build an unconstrained query that can then have batch constraints applied.
     *
     * The flag is always restored (even on exception) via a `finally` block.
     *
     * @template TReturn of mixed
     *
     * @param  Closure(): TReturn  $callback  The callback to run without constraints.
     * @return TReturn                        The return value of the callback.
     *
     * @since 4.0
     */
    public static function no_constraints(Closure $callback): mixed
    {
        $previous = static::$constraints;
        static::$constraints = false;
        // When resetting the relation where clause, we want to shift the first element
        // off of the bindings, leaving only the constraints that the developers put
        // as "extra" on the relationships, and not original relation constraints.
        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
        }
    }
    /**
     * Set the base constraints on the relation query.
     *
     * Called from the constructor. Implementations should add the minimal set of
     * WHERE clauses needed to scope the query to the parent model — typically a
     * foreign key match (e.g. `WHERE user_id = ?`).
     *
     * @return void
     *
     * @since 4.0
     */
    abstract public function add_constraints(): void;

    /**
     * Set the constraints for an eager load of the relation.
     *
     * Called during eager loading with the full set of parent models. Unlike
     * {@see addConstraints()}, this should constrain to ALL parent IDs at once
     * (e.g. `WHERE user_id IN (?)`).
     *
     * @param  array<int, TDeclaringModel>  $models  The parent models being eager-loaded.
     * @return void
     *
     * @complexity O(n) where n = count($models).
     * @since 4.0
     */
    abstract public function add_eager_constraints(array $models): void;

    /**
     * Initialize the relation on a set of models.
     *
     * Sets the default value for the relation (e.g. an empty collection for
     * hasMany, or null for hasOne) on each parent model before results are matched.
     *
     * @param  array<int, TDeclaringModel>  $models    The parent models to initialise.
     * @param  string                       $relation  The relation name (used as the property name).
     * @return array<int, TDeclaringModel>             The same models with the relation initialised.
     *
     * @since 4.0
     */
    abstract public function init_relation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents.
     *
     * Distributes the eager-loaded $results across the $models array, setting
     * the relation property on each parent to its corresponding related model(s).
     *
     * @param  array<int, TDeclaringModel>                                   $models    Parent models to populate.
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results   All eagerly loaded related models.
     * @param  string                                                         $relation  Relation name (property to set on parent).
     * @return array<int, TDeclaringModel>                                              Models with the relation populated.
     *
     * @complexity O(n+m) where n = count($models) and m = count($results).
     * @since 4.0
     */
    abstract public function match(array $models, Eloquent_Collection $results, string $relation): array;

    /**
     * Get the results of the relationship.
     *
     * For single-result relations (HasOne, BelongsTo), returns the related model
     * or null. For collection relations (HasMany, BelongsToMany), returns a Collection.
     *
     * @return TResult
     *
     * @since 4.0
     */
    abstract public function get_results(): mixed;
    /**
     * Get the relationship for eager loading.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    public function get_eager()
    {
        return $this->eager_keys_were_empty ? $this->related->new_collection() : $this->get();
    }
    /**
     * Execute the query and get the first result if it's the sole matching record.
     *
     * @param  array|string  $columns
     * @return TRelatedModel
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<TRelatedModel>
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function sole($columns = ['*'])
    {
        $result = $this->limit(2)->get($columns);
        $count = $result->count();
        if ($count === 0) {
            throw (new Model_Not_Found_Exception())->set_model($this->related::class);
        }
        if ($count > 1) {
            throw new Multiple_Records_Found_Exception($count);
        }
        return $result->first();
    }
    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    public function get($columns = ['*'])
    {
        return $this->query->get($columns);
    }
    /**
     * Touch all of the related models for the relationship.
     */
    public function touch(): void
    {
        $model = $this->get_related();
        if (!$model::is_ignoring_touch()) {
            $this->raw_update([$model->get_updated_at_column() => $model->fresh_timestamp_string()]);
        }
    }
    /**
     * Run a raw update against the base query.
     *
     * @return int
     */
    public function raw_update(array $attributes = [])
    {
        return $this->query->without_global_scopes()->update($attributes);
    }
    /**
     * Add the constraints for a relationship count query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function get_relation_existence_count_query(Builder $query, Builder $parent_query)
    {
        return $this->get_relation_existence_query($query, $parent_query, new Expression('count(*)'))->set_bindings([], 'select');
    }
    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  \Illuminate\Database\Eloquent\Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        return $query->select($columns)->where_column($this->get_qualified_parent_key_name(), '=', $this->get_existence_compare_key());
    }
    /**
     * Get a relationship join table hash.
     *
     * @param  bool  $incrementJoinCount
     * @return string
     */
    public function get_relation_count_hash($increment_join_count = true)
    {
        return 'laravel_reserved_' . ($increment_join_count ? static::$self_join_count++ : static::$self_join_count);
    }
    /**
     * Get all of the primary keys for an array of models.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  string|null  $key
     * @return array<int, int|string|null>
     */
    protected function get_keys(array $models, $key = null)
    {
        return (new Base_Collection($models))->map(fn($value) => $key ? $value->get_attribute($key) : $value->get_key())->values()->unique(null, true)->sort()->all();
    }
    /**
     * Get the query builder that will contain the relationship constraints.
     *
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    protected function get_relation_query()
    {
        return $this->query;
    }
    /**
     * Get the underlying query for the relation.
     *
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function get_query()
    {
        return $this->query;
    }
    /**
     * Get the base query builder driving the Eloquent builder.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function get_base_query()
    {
        return $this->query->get_query();
    }
    /**
     * Get a base query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function to_base()
    {
        return $this->query->to_base();
    }
    /**
     * Get the parent model of the relation.
     *
     * @return TDeclaringModel
     */
    public function get_parent()
    {
        return $this->parent;
    }
    /**
     * Get the fully-qualified parent key name.
     *
     * @return string
     */
    public function get_qualified_parent_key_name()
    {
        return $this->parent->get_qualified_key_name();
    }
    /**
     * Get the related model of the relation.
     *
     * @return TRelatedModel
     */
    public function get_related()
    {
        return $this->related;
    }
    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function created_at()
    {
        return $this->parent->get_created_at_column();
    }
    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function updated_at()
    {
        return $this->parent->get_updated_at_column();
    }
    /**
     * Get the name of the related model's "updated at" column.
     *
     * @return string
     */
    public function related_updated_at()
    {
        return $this->related->get_updated_at_column();
    }
    /**
     * Add a whereIn eager constraint for the given set of model keys to be loaded.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>|null  $query
     * @return void
     */
    protected function where_in_eager(string $where_in, string $key, array $model_keys, ?Builder $query = null)
    {
        ($query ?? $this->query)->{$where_in}($key, $model_keys);
        if ($model_keys === []) {
            $this->eager_keys_were_empty = true;
        }
    }
    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param  string  $key
     * @return string
     */
    protected function where_in_method(Model $model, $key)
    {
        return $model->get_key_name() === last(explode('.', $key)) && in_array($model->get_key_type(), ['int', 'integer']) ? 'whereIntegerInRaw' : 'whereIn';
    }
    /**
     * Prevent polymorphic relationships from being used without model mappings.
     *
     * @param  bool  $requireMorphMap
     */
    public static function require_morph_map($require_morph_map = true): void
    {
        static::$require_morph_map = $require_morph_map;
    }
    /**
     * Determine if polymorphic relationships require explicit model mapping.
     *
     * @return bool
     */
    public static function requires_morph_map()
    {
        return static::$require_morph_map;
    }
    /**
     * Define the morph map for polymorphic relations and require all morphed models to be explicitly mapped.
     *
     * @param  array<array-key, class-string<\Illuminate\Database\Eloquent\Model>>  $map
     * @param  bool  $merge
     * @return array
     */
    public static function enforce_morph_map(array $map, $merge = true)
    {
        static::require_morph_map();
        return static::morph_map($map, $merge);
    }
    /**
     * Set or get the morph map for polymorphic relations.
     *
     * @param  array<array-key, class-string<\Illuminate\Database\Eloquent\Model>>|null  $map
     * @param  bool  $merge
     * @return array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    public static function morph_map(?array $map = null, $merge = true)
    {
        $map = static::build_morph_map_from_models($map);
        if (is_array($map)) {
            static::$morph_map = $merge && static::$morph_map ? $map + static::$morph_map : $map;
        }
        return static::$morph_map;
    }
    /**
     * Builds a table-keyed array from model class names.
     *
     * @param  array<array-key, class-string<\Illuminate\Database\Eloquent\Model>>|null  $models
     * @return array<string, class-string<\Illuminate\Database\Eloquent\Model>>|null
     */
    protected static function build_morph_map_from_models(?array $models = null)
    {
        if (is_null($models) || !array_is_list($models)) {
            return $models;
        }
        return array_combine(array_map(fn(string $model) => (new $model())->get_table(), $models), $models);
    }
    /**
     * Get the model associated with a custom polymorphic type.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    public static function get_morphed_model(string $alias)
    {
        return static::$morph_map[$alias] ?? null;
    }
    /**
     * Get the alias associated with a custom polymorphic class.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $className
     * @return int|string
     */
    public static function get_morph_alias(string $class_name)
    {
        return array_search($class_name, static::$morph_map, strict: true) ?: $class_name;
    }
    /**
     * Handle dynamic method calls to the relationship.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        return $this->forward_decorated_call_to($this->query, $method, $parameters);
    }
    /**
     * Force a clone of the underlying query builder when cloning.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }
}