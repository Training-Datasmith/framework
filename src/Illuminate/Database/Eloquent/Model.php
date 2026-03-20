<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use ArrayAccess;
use Closure;
use Illuminate\Contracts\Broadcasting\Has_Broadcast_Channel;
use Illuminate\Contracts\Queue\Queueable_Collection;
use Illuminate\Contracts\Queue\Queueable_Entity;
use Illuminate\Contracts\Routing\Url_Routable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Can_Be_Escaped_When_Cast_To_String;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Connection_Resolver_Interface as Resolver;
use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Initialize;
use Illuminate\Database\Eloquent\Attributes\Scope as LocalScope;
use Illuminate\Database\Eloquent\Attributes\Use_Eloquent_Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Belongs_To_Many;
use Illuminate\Database\Eloquent\Relations\Concerns\As_Pivot;
use Illuminate\Database\Eloquent\Relations\Has_Many_Through;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable as SupportStringable;
use Illuminate\Support\Traits\Forwards_Calls;
use Json_Exception;
use JsonSerializable;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Stringable;
abstract class Model implements Arrayable, ArrayAccess, Can_Be_Escaped_When_Cast_To_String, Has_Broadcast_Channel, Jsonable, JsonSerializable, Queueable_Entity, Stringable, Url_Routable
{
    use Concerns\Has_Attributes;
    use Concerns\Has_Events;
    use Concerns\Has_Global_Scopes;
    use Concerns\Has_Relationships;
    use Concerns\Has_Timestamps;
    use Concerns\Has_Unique_Ids;
    use Concerns\Hides_Attributes;
    use Concerns\Guards_Attributes;
    use Concerns\Prevents_Circular_Recursion;
    use Concerns\Transforms_To_Resource;
    use Forwards_Calls;
    /** @use HasCollection<\Illuminate\Database\Eloquent\Collection<array-key, static & self>> */
    use Has_Collection;
    /**
     * The connection name for the model.
     *
     * @var \UnitEnum|string|null
     */
    protected $connection;
    /**
     * The table associated with the model.
     *
     * @var string|null
     */
    protected $table;
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primary_key = 'id';
    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $key_type = 'int';
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = [];
    /**
     * The relationship counts that should be eager loaded on every query.
     *
     * @var array
     */
    protected $with_count = [];
    /**
     * Indicates whether lazy loading will be prevented on this model.
     *
     * @var bool
     */
    public $prevents_lazy_loading = false;
    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    protected $per_page = 15;
    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;
    /**
     * Indicates if the model was inserted during the object's lifecycle.
     *
     * @var bool
     */
    public $was_recently_created = false;
    /**
     * Indicates that the object's string representation should be escaped when __toString is invoked.
     *
     * @var bool
     */
    protected $escape_when_casting_to_string = false;
    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected static $resolver;
    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher|null
     */
    protected static $dispatcher;
    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];
    /**
     * The callbacks that should be executed after the model has booted.
     *
     * @var array
     */
    protected static $booted_callbacks = [];
    /**
     * The array of trait initializers that will be called on each new instance.
     *
     * @var array
     */
    protected static $trait_initializers = [];
    /**
     * The array of global scopes on the model.
     *
     * @var array
     */
    protected static $global_scopes = [];
    /**
     * The list of models classes that should not be affected with touch.
     *
     * @var array
     */
    protected static $ignore_on_touch = [];
    /**
     * Indicates whether lazy loading should be restricted on all models.
     *
     * @var bool
     */
    protected static $models_should_prevent_lazy_loading = false;
    /**
     * Indicates whether relations should be automatically loaded on all models when they are accessed.
     *
     * @var bool
     */
    protected static $models_should_automatically_eager_load_relationships = false;
    /**
     * The callback that is responsible for handling lazy loading violations.
     *
     * @var (callable(self, string))|null
     */
    protected static $lazy_loading_violation_callback;
    /**
     * Indicates if an exception should be thrown instead of silently discarding non-fillable attributes.
     *
     * @var bool
     */
    protected static $models_should_prevent_silently_discarding_attributes = false;
    /**
     * The callback that is responsible for handling discarded attribute violations.
     *
     * @var (callable(self, array))|null
     */
    protected static $discarded_attribute_violation_callback;
    /**
     * Indicates if an exception should be thrown when trying to access a missing attribute on a retrieved model.
     *
     * @var bool
     */
    protected static $models_should_prevent_accessing_missing_attributes = false;
    /**
     * The callback that is responsible for handling missing attribute violations.
     *
     * @var (callable(self, string))|null
     */
    protected static $missing_attribute_violation_callback;
    /**
     * Indicates if broadcasting is currently enabled.
     *
     * @var bool
     */
    protected static $is_broadcasting = true;
    /**
     * The Eloquent query builder class to use for the model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Builder<*>>
     */
    protected static string $builder = Builder::class;
    /**
     * The Eloquent collection class to use for the model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Collection<*, *>>
     */
    protected static string $collection_class = Collection::class;
    /**
     * Cache of soft deletable models.
     *
     * @var array<class-string<self>, bool>
     */
    protected static array $is_soft_deletable;
    /**
     * Cache of prunable models.
     *
     * @var array<class-string<self>, bool>
     */
    protected static array $is_prunable;
    /**
     * Cache of mass prunable models.
     *
     * @var array<class-string<self>, bool>
     */
    protected static array $is_mass_prunable;
    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    public const CREATED_AT = 'created_at';
    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    public const UPDATED_AT = 'updated_at';
    /**
     * Create a new Eloquent model instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->boot_if_not_booted();
        $this->initialize_traits();
        $this->sync_original();
        $this->fill($attributes);
    }
    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function boot_if_not_booted()
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            $this->fire_model_event('booting', false);
            static::booting();
            static::boot();
            static::booted();
            static::$booted_callbacks[static::class] ??= [];
            foreach (static::$booted_callbacks[static::class] as $callback) {
                $callback();
            }
            $this->fire_model_event('booted', false);
        }
    }
    /**
     * Perform any actions required before the model boots.
     *
     * @return void
     */
    protected static function booting()
    {
    }
    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        static::boot_traits();
    }
    /**
     * Boot all of the bootable traits on the model.
     *
     * @return void
     */
    protected static function boot_traits()
    {
        $class = static::class;
        $booted = [];
        static::$trait_initializers[$class] = [];
        $uses = class_uses_recursive($class);
        $conventional_boot_methods = array_map(static fn(string $trait): string => 'boot' . class_basename($trait), $uses);
        $conventional_init_methods = array_map(static fn(string $trait): string => 'initialize' . class_basename($trait), $uses);
        foreach ((new ReflectionClass($class))->get_methods() as $method) {
            if (!in_array($method->get_name(), $booted) && $method->is_static() && (in_array($method->get_name(), $conventional_boot_methods) || $method->get_attributes(Boot::class) !== [])) {
                $method->invoke(null);
                $booted[] = $method->get_name();
            }
            if (in_array($method->get_name(), $conventional_init_methods) || $method->get_attributes(Initialize::class) !== []) {
                static::$trait_initializers[$class][] = $method->get_name();
            }
        }
        static::$trait_initializers[$class] = array_unique(static::$trait_initializers[$class]);
    }
    /**
     * Initialize any initializable traits on the model.
     *
     * @return void
     */
    protected function initialize_traits()
    {
        foreach (static::$trait_initializers[static::class] as $method) {
            $this->{$method}();
        }
    }
    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
    }
    /**
     * Register a closure to be executed after the model has booted.
     *
     * @return void
     */
    protected static function when_booted(Closure $callback)
    {
        static::$booted_callbacks[static::class] ??= [];
        static::$booted_callbacks[static::class][] = $callback;
    }
    /**
     * Clear the list of booted models so they will be re-booted.
     */
    public static function clear_booted_models(): void
    {
        static::$booted = [];
        static::$booted_callbacks = [];
        static::$global_scopes = [];
    }
    /**
     * Disables relationship model touching for the current class during given callback scope.
     */
    public static function without_touching(callable $callback): void
    {
        static::without_touching_on([static::class], $callback);
    }
    /**
     * Disables relationship model touching for the given model classes during given callback scope.
     */
    public static function without_touching_on(array $models, callable $callback): void
    {
        static::$ignore_on_touch = array_values(array_merge(static::$ignore_on_touch, $models));
        try {
            $callback();
        } finally {
            static::$ignore_on_touch = array_values(array_diff(static::$ignore_on_touch, $models));
        }
    }
    /**
     * Determine if the given model is ignoring touches.
     *
     * @param  string|null  $class
     * @return bool
     */
    public static function is_ignoring_touch($class = null)
    {
        $class = $class ?: static::class;
        if (!get_class_vars($class)['timestamps'] || !$class::UPDATED_AT) {
            return true;
        }
        foreach (static::$ignore_on_touch as $ignored_class) {
            if ($class === $ignored_class || is_subclass_of($class, $ignored_class)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Indicate that models should prevent lazy loading, silently discarding attributes, and accessing missing attributes.
     */
    public static function should_be_strict(bool $should_be_strict = true): void
    {
        static::prevent_lazy_loading($should_be_strict);
        static::prevent_silently_discarding_attributes($should_be_strict);
        static::prevent_accessing_missing_attributes($should_be_strict);
    }
    /**
     * Prevent model relationships from being lazy loaded.
     *
     * @param  bool  $value
     */
    public static function prevent_lazy_loading($value = true): void
    {
        static::$models_should_prevent_lazy_loading = $value;
    }
    /**
     * Determine if model relationships should be automatically eager loaded when accessed.
     *
     * @param  bool  $value
     */
    public static function automatically_eager_load_relationships($value = true): void
    {
        static::$models_should_automatically_eager_load_relationships = $value;
    }
    /**
     * Register a callback that is responsible for handling lazy loading violations.
     *
     * @param  (callable(self, string))|null  $callback
     */
    public static function handle_lazy_loading_violation_using(?callable $callback): void
    {
        static::$lazy_loading_violation_callback = $callback;
    }
    /**
     * Prevent non-fillable attributes from being silently discarded.
     *
     * @param  bool  $value
     */
    public static function prevent_silently_discarding_attributes($value = true): void
    {
        static::$models_should_prevent_silently_discarding_attributes = $value;
    }
    /**
     * Register a callback that is responsible for handling discarded attribute violations.
     *
     * @param  (callable(self, array))|null  $callback
     */
    public static function handle_discarded_attribute_violation_using(?callable $callback): void
    {
        static::$discarded_attribute_violation_callback = $callback;
    }
    /**
     * Prevent accessing missing attributes on retrieved models.
     *
     * @param  bool  $value
     */
    public static function prevent_accessing_missing_attributes($value = true): void
    {
        static::$models_should_prevent_accessing_missing_attributes = $value;
    }
    /**
     * Register a callback that is responsible for handling missing attribute violations.
     *
     * @param  (callable(self, string))|null  $callback
     */
    public static function handle_missing_attribute_violation_using(?callable $callback): void
    {
        static::$missing_attribute_violation_callback = $callback;
    }
    /**
     * Execute a callback without broadcasting any model events for all model types.
     *
     * @return mixed
     */
    public static function without_broadcasting(callable $callback)
    {
        $is_broadcasting = static::$is_broadcasting;
        static::$is_broadcasting = false;
        try {
            return $callback();
        } finally {
            static::$is_broadcasting = $is_broadcasting;
        }
    }
    /**
     * Fill the model with an array of attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        $totally_guarded = $this->totally_guarded();
        $fillable = $this->fillable_from_array($attributes);
        foreach ($fillable as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->is_fillable($key)) {
                $this->set_attribute($key, $value);
            } elseif ($totally_guarded || static::prevents_silently_discarding_attributes()) {
                if (isset(static::$discarded_attribute_violation_callback)) {
                    call_user_func(static::$discarded_attribute_violation_callback, $this, [$key]);
                } else {
                    throw new Mass_Assignment_Exception(sprintf('Add [%s] to fillable property to allow mass assignment on [%s].', $key, static::class));
                }
            }
        }
        if (count($attributes) !== count($fillable) && static::prevents_silently_discarding_attributes()) {
            $keys = array_diff(array_keys($attributes), array_keys($fillable));
            if (isset(static::$discarded_attribute_violation_callback)) {
                call_user_func(static::$discarded_attribute_violation_callback, $this, $keys);
            } else {
                throw new Mass_Assignment_Exception(sprintf('Add fillable property [%s] to allow mass assignment on [%s].', implode(', ', $keys), static::class));
            }
        }
        return $this;
    }
    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function force_fill(array $attributes)
    {
        return static::unguarded(fn() => $this->fill($attributes));
    }
    /**
     * Qualify the given column name by the model's table.
     *
     * @return string
     */
    public function qualify_column(string $column)
    {
        if (str_contains($column, '.')) {
            return $column;
        }
        return $this->get_table() . '.' . $column;
    }
    /**
     * Qualify the given columns with the model's table.
     *
     * @param  array  $columns
     * @return array
     */
    public function qualify_columns($columns)
    {
        return (new Base_Collection($columns))->map(fn(string $column) => $this->qualify_column($column))->all();
    }
    /**
     * Create a new instance of the given model.
     *
     * @param  array<string, mixed>  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function new_instance($attributes = [], $exists = false)
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static();
        $model->exists = $exists;
        $model->set_connection($this->get_connection_name());
        $model->set_table($this->get_table());
        $model->merge_casts($this->casts);
        $model->fill((array) $attributes);
        return $model;
    }
    /**
     * Create a new model instance that is existing.
     *
     * @param  array<string, mixed>  $attributes
     * @param  \UnitEnum|string|null  $connection
     * @return static
     */
    public function new_from_builder($attributes = [], $connection = null)
    {
        $model = $this->new_instance([], true);
        $model->set_raw_attributes((array) $attributes, true);
        $model->set_connection($connection ?? $this->get_connection_name());
        $model->fire_model_event('retrieved', false);
        return $model;
    }
    /**
     * Begin querying the model on a given connection.
     *
     * @param  \UnitEnum|string|null  $connection
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function on($connection = null)
    {
        // First we will just create a fresh instance of this model, and then we can set the
        // connection on the model so that it is used for the queries we execute, as well
        // as being set on every relation we retrieve without a custom connection name.
        return (new static())->set_connection($connection)->new_query();
    }
    /**
     * Begin querying the model on the write connection.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function on_write_connection()
    {
        return static::query()->use_write_pdo();
    }
    /**
     * Get all of the models from the database.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function all($columns = ['*'])
    {
        return static::query()->get(is_array($columns) ? $columns : func_get_args());
    }
    /**
     * Begin querying a model with eager loading.
     *
     * @param  array|string  $relations
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function with($relations)
    {
        return static::query()->with(is_string($relations) ? func_get_args() : $relations);
    }
    /**
     * Eager load relations on the model.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load($relations)
    {
        $query = $this->new_query_without_relationships()->with(is_string($relations) ? func_get_args() : $relations);
        $query->eager_load_relations([$this]);
        return $this;
    }
    /**
     * Eager load relationships on the polymorphic relation of a model.
     *
     * @param  string  $relation
     * @return $this
     */
    public function load_morph($relation, array $relations)
    {
        if (!$this->{$relation}) {
            return $this;
        }
        $class_name = $this->{$relation}::class;
        $this->{$relation}->load($relations[$class_name] ?? []);
        return $this;
    }
    /**
     * Eager load relations on the model if they are not already eager loaded.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load_missing($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;
        $this->new_collection([$this])->load_missing($relations);
        return $this;
    }
    /**
     * Eager load relation's column aggregations on the model.
     *
     * @param  array|string  $relations
     * @param  string  $column
     * @param  string|null  $function
     * @return $this
     */
    public function load_aggregate($relations, $column, $function = null)
    {
        $this->new_collection([$this])->load_aggregate($relations, $column, $function);
        return $this;
    }
    /**
     * Eager load relation counts on the model.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load_count($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;
        return $this->load_aggregate($relations, '*', 'count');
    }
    /**
     * Eager load relation max column values on the model.
     *
     * @param  array|string  $relations
     * @param  string  $column
     * @return $this
     */
    public function load_max($relations, $column)
    {
        return $this->load_aggregate($relations, $column, 'max');
    }
    /**
     * Eager load relation min column values on the model.
     *
     * @param  array|string  $relations
     * @param  string  $column
     * @return $this
     */
    public function load_min($relations, $column)
    {
        return $this->load_aggregate($relations, $column, 'min');
    }
    /**
     * Eager load relation's column summations on the model.
     *
     * @param  array|string  $relations
     * @param  string  $column
     * @return $this
     */
    public function load_sum($relations, $column)
    {
        return $this->load_aggregate($relations, $column, 'sum');
    }
    /**
     * Eager load relation average column values on the model.
     *
     * @param  array|string  $relations
     * @param  string  $column
     * @return $this
     */
    public function load_avg($relations, $column)
    {
        return $this->load_aggregate($relations, $column, 'avg');
    }
    /**
     * Eager load related model existence values on the model.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load_exists($relations)
    {
        return $this->load_aggregate($relations, '*', 'exists');
    }
    /**
     * Eager load relationship column aggregation on the polymorphic relation of a model.
     *
     * @param  string  $relation
     * @param  string  $column
     * @param  string|null  $function
     * @return $this
     */
    public function load_morph_aggregate($relation, array $relations, $column, $function = null)
    {
        if (!$this->{$relation}) {
            return $this;
        }
        $class_name = $this->{$relation}::class;
        $this->{$relation}->load_aggregate($relations[$class_name] ?? [], $column, $function);
        return $this;
    }
    /**
     * Eager load relationship counts on the polymorphic relation of a model.
     *
     * @param  string  $relation
     * @return $this
     */
    public function load_morph_count($relation, array $relations)
    {
        return $this->load_morph_aggregate($relation, $relations, '*', 'count');
    }
    /**
     * Eager load relationship max column values on the polymorphic relation of a model.
     *
     * @param  string  $relation
     * @param  string  $column
     * @return $this
     */
    public function load_morph_max($relation, array $relations, $column)
    {
        return $this->load_morph_aggregate($relation, $relations, $column, 'max');
    }
    /**
     * Eager load relationship min column values on the polymorphic relation of a model.
     *
     * @param  string  $relation
     * @param  string  $column
     * @return $this
     */
    public function load_morph_min($relation, array $relations, $column)
    {
        return $this->load_morph_aggregate($relation, $relations, $column, 'min');
    }
    /**
     * Eager load relationship column summations on the polymorphic relation of a model.
     *
     * @param  string  $relation
     * @param  string  $column
     * @return $this
     */
    public function load_morph_sum($relation, array $relations, $column)
    {
        return $this->load_morph_aggregate($relation, $relations, $column, 'sum');
    }
    /**
     * Eager load relationship average column values on the polymorphic relation of a model.
     *
     * @param  string  $relation
     * @param  string  $column
     * @return $this
     */
    public function load_morph_avg($relation, array $relations, $column)
    {
        return $this->load_morph_aggregate($relation, $relations, $column, 'avg');
    }
    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @return int
     */
    protected function increment($column, $amount = 1, array $extra = [])
    {
        return $this->increment_or_decrement($column, $amount, $extra, 'increment');
    }
    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @return int
     */
    protected function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->increment_or_decrement($column, $amount, $extra, 'decrement');
    }
    /**
     * Run the increment or decrement method on the model.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @param  string  $method
     * @return int
     */
    protected function increment_or_decrement($column, $amount, array $extra, $method)
    {
        if (!$this->exists) {
            return $this->new_query_without_relationships()->{$method}($column, $amount, $extra);
        }
        $this->{$column} = $this->is_class_deviable($column) ? $this->deviate_class_castable_attribute($method, $column, $amount) : $this->{$column} + ($method === 'increment' ? $amount : $amount * -1);
        $this->force_fill($extra);
        if ($this->fire_model_event('updating') === false) {
            return false;
        }
        if ($this->is_class_deviable($column)) {
            $amount = (clone $this)->set_attribute($column, $amount)->get_attribute_from_array($column);
        }
        return tap($this->set_keys_for_save_query($this->new_query_without_scopes())->{$method}($column, $amount, $extra), function () use ($column): void {
            $this->sync_changes();
            $this->fire_model_event('updated', false);
            $this->sync_original_attribute($column);
        });
    }
    /**
     * Update the model in the database.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     * @return bool
     */
    public function update(array $attributes = [], array $options = [])
    {
        if (!$this->exists) {
            return false;
        }
        return $this->fill($attributes)->save($options);
    }
    /**
     * Update the model in the database within a transaction.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     * @return bool
     *
     * @throws \Throwable
     */
    public function update_or_fail(array $attributes = [], array $options = [])
    {
        if (!$this->exists) {
            return false;
        }
        return $this->fill($attributes)->save_or_fail($options);
    }
    /**
     * Update the model in the database without raising any events.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     * @return bool
     */
    public function update_quietly(array $attributes = [], array $options = [])
    {
        if (!$this->exists) {
            return false;
        }
        return $this->fill($attributes)->save_quietly($options);
    }
    /**
     * Increment a column's value by a given amount without raising any events.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @return int
     */
    protected function increment_quietly($column, $amount = 1, array $extra = [])
    {
        return static::without_events(fn() => $this->increment_or_decrement($column, $amount, $extra, 'increment'));
    }
    /**
     * Decrement a column's value by a given amount without raising any events.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @return int
     */
    protected function decrement_quietly($column, $amount = 1, array $extra = [])
    {
        return static::without_events(fn() => $this->increment_or_decrement($column, $amount, $extra, 'decrement'));
    }
    /**
     * Save the model and all of its relationships.
     *
     * @return bool
     */
    public function push()
    {
        return $this->without_recursion(function (): bool {
            if (!$this->save()) {
                return false;
            }
            // To sync all of the relationships to the database, we will simply spin through
            // the relationships and save each model via this "push" method, which allows
            // us to recurse into all of these nested relations for the model instance.
            foreach ($this->relations as $models) {
                $models = $models instanceof Collection ? $models->all() : [$models];
                foreach (array_filter($models) as $model) {
                    if (!$model->push()) {
                        return false;
                    }
                }
            }
            return true;
        }, true);
    }
    /**
     * Save the model and all of its relationships without raising any events to the parent model.
     *
     * @return bool
     */
    public function push_quietly()
    {
        return static::without_events(fn() => $this->push());
    }
    /**
     * Save the model to the database without raising any events.
     *
     * @return bool
     */
    public function save_quietly(array $options = [])
    {
        return static::without_events(fn() => $this->save($options));
    }
    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        $this->merge_attributes_from_cached_casts();
        $query = $this->new_model_query();
        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fire_model_event('saving') === false) {
            return false;
        }
        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->is_dirty() ? $this->perform_update($query) : true;
        } else {
            $saved = $this->perform_insert($query);
            if (!$this->get_connection_name() && $connection = $query->get_connection()) {
                $this->set_connection($connection->get_name());
            }
        }
        // If the model is successfully saved, we need to do a few more things once
        // that is done. We will call the "saved" method here to run any actions
        // we need to happen after a model gets successfully saved right here.
        if ($saved) {
            $this->finish_save($options);
        }
        return $saved;
    }
    /**
     * Save the model to the database within a transaction.
     *
     * @return bool
     * @throws \Throwable
     */
    public function save_or_fail(array $options = [])
    {
        return $this->get_connection()->transaction(fn() => $this->save($options));
    }
    /**
     * Perform any actions that are necessary after the model is saved.
     *
     * @return void
     */
    protected function finish_save(array $options)
    {
        $this->fire_model_event('saved', false);
        if ($this->is_dirty() && ($options['touch'] ?? true)) {
            $this->touch_owners();
        }
        $this->sync_original();
    }
    /**
     * Perform a model update operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return bool
     */
    protected function perform_update(Builder $query)
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fire_model_event('updating') === false) {
            return false;
        }
        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->uses_timestamps()) {
            $this->update_timestamps();
        }
        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->get_dirty_for_update();
        if (count($dirty) > 0) {
            $this->set_keys_for_save_query($query)->update($dirty);
            $this->sync_changes();
            $this->fire_model_event('updated', false);
        }
        return true;
    }
    /**
     * Set the keys for a select query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function set_keys_for_select_query($query)
    {
        $query->where($this->get_key_name(), '=', $this->get_key_for_select_query());
        return $query;
    }
    /**
     * Get the primary key value for a select query.
     *
     * @return mixed
     */
    protected function get_key_for_select_query()
    {
        return $this->original[$this->get_key_name()] ?? $this->get_key();
    }
    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function set_keys_for_save_query($query)
    {
        $query->where($this->get_key_name(), '=', $this->get_key_for_save_query());
        return $query;
    }
    /**
     * Get the primary key value for a save query.
     *
     * @return mixed
     */
    protected function get_key_for_save_query()
    {
        return $this->original[$this->get_key_name()] ?? $this->get_key();
    }
    /**
     * Perform a model insert operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return bool
     */
    protected function perform_insert(Builder $query)
    {
        if ($this->uses_unique_ids()) {
            $this->set_unique_ids();
        }
        if ($this->fire_model_event('creating') === false) {
            return false;
        }
        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->uses_timestamps()) {
            $this->update_timestamps();
        }
        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final inserted ID for this
        // table from the database. Not all tables have to be incrementing though.
        $attributes = $this->get_attributes_for_insert();
        if ($this->get_incrementing()) {
            $this->insert_and_set_id($query, $attributes);
        } else {
            if (empty($attributes)) {
                return true;
            }
            $query->insert($attributes);
        }
        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;
        $this->was_recently_created = true;
        $this->fire_model_event('created', false);
        return true;
    }
    /**
     * Insert the given attributes and set the ID on the model.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  array<string, mixed>  $attributes
     * @return void
     */
    protected function insert_and_set_id(Builder $query, $attributes)
    {
        $id = $query->insert_get_id($attributes, $key_name = $this->get_key_name());
        $this->set_attribute($key_name, $id);
    }
    /**
     * Destroy the models for the given IDs.
     *
     * @param  \Illuminate\Support\Collection|array|int|string  $ids
     * @return int
     */
    public static function destroy($ids)
    {
        if ($ids instanceof Eloquent_Collection) {
            $ids = $ids->model_keys();
        }
        if ($ids instanceof Base_Collection) {
            $ids = $ids->all();
        }
        $ids = is_array($ids) ? $ids : func_get_args();
        if (count($ids) === 0) {
            return 0;
        }
        // We will actually pull the models from the database table and call delete on
        // each of them individually so that their events get fired properly with a
        // correct set of attributes in case the developers wants to check these.
        $key = ($instance = new static())->get_key_name();
        $count = 0;
        foreach ($instance->where_in($key, $ids)->get() as $model) {
            if ($model->delete()) {
                $count++;
            }
        }
        return $count;
    }
    /**
     * Delete the model from the database.
     *
     * @return bool|null
     *
     * @throws \LogicException
     */
    public function delete()
    {
        $this->merge_attributes_from_cached_casts();
        if (is_null($this->get_key_name())) {
            throw new LogicException('No primary key defined on model.');
        }
        // If the model doesn't exist, there is nothing to delete so we'll just return
        // immediately and not do anything else. Otherwise, we will continue with a
        // deletion process on the model, firing the proper events, and so forth.
        if (!$this->exists) {
            return;
        }
        if ($this->fire_model_event('deleting') === false) {
            return false;
        }
        // Here, we'll touch the owning models, verifying these timestamps get updated
        // for the models. This will allow any caching to get broken on the parents
        // by the timestamp. Then we will go ahead and delete the model instance.
        $this->touch_owners();
        $this->perform_delete_on_model();
        // Once the model has been deleted, we will fire off the deleted event so that
        // the developers may hook into post-delete operations. We will then return
        // a boolean true as the delete is presumably successful on the database.
        $this->fire_model_event('deleted', false);
        return true;
    }
    /**
     * Delete the model from the database without raising any events.
     *
     * @return bool
     */
    public function delete_quietly()
    {
        return static::without_events(fn() => $this->delete());
    }
    /**
     * Delete the model from the database within a transaction.
     *
     * @return bool|null
     *
     * @throws \Throwable
     */
    public function delete_or_fail()
    {
        if (!$this->exists) {
            return false;
        }
        return $this->get_connection()->transaction(fn() => $this->delete());
    }
    /**
     * Force a hard delete on a soft deleted model.
     *
     * This method protects developers from running forceDelete when the trait is missing.
     *
     * @return bool|null
     */
    public function force_delete()
    {
        return $this->delete();
    }
    /**
     * Force a hard destroy on a soft deleted model.
     *
     * This method protects developers from running forceDestroy when the trait is missing.
     *
     * @param  \Illuminate\Support\Collection|array|int|string  $ids
     * @return bool|null
     */
    public static function force_destroy($ids)
    {
        return static::destroy($ids);
    }
    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function perform_delete_on_model()
    {
        $this->set_keys_for_save_query($this->new_model_query())->delete();
        $this->exists = false;
    }
    /**
     * Begin querying the model.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function query()
    {
        return (new static())->new_query();
    }
    /**
     * Get a new query builder for the model's table.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function new_query()
    {
        return $this->register_global_scopes($this->new_query_without_scopes());
    }
    /**
     * Get a new query builder that doesn't have any global scopes or eager loading.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function new_model_query()
    {
        return $this->new_eloquent_builder($this->new_base_query_builder())->set_model($this);
    }
    /**
     * Get a new query builder with no relationships loaded.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function new_query_without_relationships()
    {
        return $this->register_global_scopes($this->new_model_query());
    }
    /**
     * Register the global scopes for this builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $builder
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function register_global_scopes($builder)
    {
        foreach ($this->get_global_scopes() as $identifier => $scope) {
            $builder->with_global_scope($identifier, $scope);
        }
        return $builder;
    }
    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function new_query_without_scopes()
    {
        return $this->new_model_query()->with($this->with)->with_count($this->with_count);
    }
    /**
     * Get a new query instance without a given scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function new_query_without_scope($scope)
    {
        return $this->new_query()->without_global_scope($scope);
    }
    /**
     * Get a new query to restore one or more models by their queueable IDs.
     *
     * @param  array|int  $ids
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function new_query_for_restoration($ids)
    {
        return $this->new_query_without_scopes()->where_key($ids);
    }
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder<*>
     */
    public function new_eloquent_builder($query)
    {
        $builder_class = $this->resolve_custom_builder_class();
        if ($builder_class && is_subclass_of($builder_class, Builder::class)) {
            return new $builder_class($query);
        }
        return new static::$builder($query);
    }
    /**
     * Resolve the custom Eloquent builder class from the model attributes.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Builder>|false
     */
    protected function resolve_custom_builder_class()
    {
        $attributes = (new ReflectionClass($this))->get_attributes(Use_Eloquent_Builder::class);
        return !empty($attributes) ? $attributes[0]->new_instance()->builder_class : false;
    }
    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function new_base_query_builder()
    {
        return $this->get_connection()->query();
    }
    /**
     * Create a new pivot model instance.
     *
     * @param  array<string, mixed>  $attributes
     * @param  string  $table
     * @param  bool  $exists
     * @param  string|null  $using
     * @return \Illuminate\Database\Eloquent\Relations\Pivot
     */
    public function new_pivot(self $parent, array $attributes, $table, $exists, $using = null)
    {
        return $using ? $using::from_raw_attributes($parent, $attributes, $table, $exists) : Pivot::from_attributes($parent, $attributes, $table, $exists);
    }
    /**
     * Determine if the model has a given scope.
     *
     * @return bool
     */
    public function has_named_scope(string $scope)
    {
        return method_exists($this, 'scope_' . $scope) || static::is_scope_method_with_attribute($scope);
    }
    /**
     * Apply the given named scope if possible.
     *
     * @param  string  $scope
     * @return mixed
     */
    public function call_named_scope($scope, array $parameters = [])
    {
        if (static::is_scope_method_with_attribute($scope)) {
            return $this->{$scope}(...$parameters);
        }
        return $this->{'scope_' . $scope}(...$parameters);
    }
    /**
     * Determine if the given method has a scope attribute.
     *
     * @return bool
     */
    protected static function is_scope_method_with_attribute(string $method)
    {
        return method_exists(static::class, $method) && (new ReflectionMethod(static::class, $method))->get_attributes(Local_Scope::class) !== [];
    }
    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function to_array()
    {
        return $this->without_recursion(fn(): array => array_merge($this->attributes_to_array(), $this->relations_to_array()), fn() => $this->attributes_to_array());
    }
    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     *
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function to_json($options = 0)
    {
        try {
            $json = json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (Json_Exception $e) {
            throw Json_Encoding_Exception::for_model($this, $e->get_message());
        }
        return $json;
    }
    /**
     * Convert the model instance to pretty print formatted JSON.
     *
     * @return string
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function to_pretty_json(int $options = 0)
    {
        return $this->to_json(JSON_PRETTY_PRINT | $options);
    }
    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): mixed
    {
        return $this->to_array();
    }
    /**
     * Reload a fresh model instance from the database.
     *
     * @param  array|string  $with
     * @return static|null
     */
    public function fresh($with = [])
    {
        if (!$this->exists) {
            return;
        }
        return $this->set_keys_for_select_query($this->new_query_without_scopes())->use_write_pdo()->with(is_string($with) ? func_get_args() : $with)->first();
    }
    /**
     * Reload the current model instance with fresh attributes from the database.
     *
     * @return $this
     */
    public function refresh()
    {
        if (!$this->exists) {
            return $this;
        }
        $this->set_raw_attributes($this->set_keys_for_select_query($this->new_query_without_scopes())->use_write_pdo()->first_or_fail()->attributes);
        $this->load((new Base_Collection($this->relations))->reject(fn($relation): bool => $relation instanceof Pivot || is_object($relation) && in_array(As_Pivot::class, class_uses_recursive($relation), true))->keys()->all());
        $this->sync_original();
        return $this;
    }
    /**
     * Clone the model into a new, non-existing instance.
     *
     * @return static
     */
    public function replicate(?array $except = null)
    {
        $defaults = array_values(array_filter([$this->get_key_name(), $this->get_created_at_column(), $this->get_updated_at_column(), ...$this->unique_ids(), 'laravel_through_key']));
        $attributes = Arr::except($this->get_attributes(), $except ? array_unique(array_merge($except, $defaults)) : $defaults);
        return tap(new static(), function ($instance) use ($attributes): void {
            $instance->set_raw_attributes($attributes);
            $instance->set_relations($this->relations);
            $instance->fire_model_event('replicating', false);
        });
    }
    /**
     * Clone the model into a new, non-existing instance without raising any events.
     *
     * @return static
     */
    public function replicate_quietly(?array $except = null)
    {
        return static::without_events(fn() => $this->replicate($except));
    }
    /**
     * Determine if two models have the same ID and belong to the same table.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @return bool
     */
    public function is($model)
    {
        return !is_null($model) && $this->get_key() === $model->get_key() && $this->get_table() === $model->get_table() && $this->get_connection_name() === $model->get_connection_name();
    }
    /**
     * Determine if two models are not the same.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @return bool
     */
    public function is_not($model)
    {
        return !$this->is($model);
    }
    /**
     * Get the database connection for the model.
     *
     * @return \Illuminate\Database\Connection
     */
    public function get_connection()
    {
        return static::resolve_connection($this->get_connection_name());
    }
    /**
     * Get the current connection name for the model.
     *
     * @return string|null
     */
    public function get_connection_name()
    {
        return enum_value($this->connection);
    }
    /**
     * Set the connection associated with the model.
     *
     * @param  \UnitEnum|string|null  $name
     * @return $this
     */
    public function set_connection($name)
    {
        $this->connection = $name;
        return $this;
    }
    /**
     * Resolve a connection instance.
     *
     * @param  \UnitEnum|string|null  $connection
     * @return \Illuminate\Database\Connection
     */
    public static function resolve_connection($connection = null)
    {
        return static::$resolver->connection($connection);
    }
    /**
     * Get the connection resolver instance.
     *
     * @return \Illuminate\Database\ConnectionResolverInterface|null
     */
    public static function get_connection_resolver()
    {
        return static::$resolver;
    }
    /**
     * Set the connection resolver instance.
     */
    public static function set_connection_resolver(Resolver $resolver): void
    {
        static::$resolver = $resolver;
    }
    /**
     * Unset the connection resolver for models.
     */
    public static function unset_connection_resolver(): void
    {
        static::$resolver = null;
    }
    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function get_table()
    {
        return $this->table ?? Str::snake(Str::plural_studly(class_basename($this)));
    }
    /**
     * Set the table associated with the model.
     *
     * @param  string  $table
     * @return $this
     */
    public function set_table($table)
    {
        $this->table = $table;
        return $this;
    }
    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function get_key_name()
    {
        return $this->primary_key;
    }
    /**
     * Set the primary key for the model.
     *
     * @param  string  $key
     * @return $this
     */
    public function set_key_name($key)
    {
        $this->primary_key = $key;
        return $this;
    }
    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function get_qualified_key_name()
    {
        return $this->qualify_column($this->get_key_name());
    }
    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function get_key_type()
    {
        return $this->key_type;
    }
    /**
     * Set the data type for the primary key.
     *
     * @param  string  $type
     * @return $this
     */
    public function set_key_type($type)
    {
        $this->key_type = $type;
        return $this;
    }
    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function get_incrementing()
    {
        return $this->incrementing;
    }
    /**
     * Set whether IDs are incrementing.
     *
     * @param  bool  $value
     * @return $this
     */
    public function set_incrementing($value)
    {
        $this->incrementing = $value;
        return $this;
    }
    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function get_key()
    {
        return $this->get_attribute($this->get_key_name());
    }
    /**
     * Get the queueable identity for the entity.
     *
     * @return mixed
     */
    public function get_queueable_id()
    {
        return $this->get_key();
    }
    /**
     * Get the queueable relationships for the entity.
     *
     * @return array
     */
    public function get_queueable_relations()
    {
        return $this->without_recursion(function (): array {
            $relations = [];
            foreach ($this->get_relations() as $key => $relation) {
                if (!method_exists($this, $key)) {
                    continue;
                }
                $relations[] = $key;
                if ($relation instanceof Queueable_Collection) {
                    foreach ($relation->get_queueable_relations() as $collection_value) {
                        $relations[] = $key . '.' . $collection_value;
                    }
                }
                if ($relation instanceof Queueable_Entity) {
                    foreach ($relation->get_queueable_relations() as $entity_value) {
                        $relations[] = $key . '.' . $entity_value;
                    }
                }
            }
            return array_unique($relations);
        }, []);
    }
    /**
     * Get the queueable connection for the entity.
     *
     * @return string|null
     */
    public function get_queueable_connection()
    {
        return $this->get_connection_name();
    }
    /**
     * Get the value of the model's route key.
     *
     * @return mixed
     */
    public function get_route_key()
    {
        return $this->get_attribute($this->get_route_key_name());
    }
    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function get_route_key_name()
    {
        return $this->get_key_name();
    }
    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolve_route_binding($value, $field = null)
    {
        return $this->resolve_route_binding_query($this, $value, $field)->first();
    }
    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolve_soft_deletable_route_binding($value, $field = null)
    {
        return $this->resolve_route_binding_query($this, $value, $field)->with_trashed()->first();
    }
    /**
     * Retrieve the child model for a bound value.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolve_child_route_binding($child_type, $value, $field)
    {
        return $this->resolve_child_route_binding_query($child_type, $value, $field)->first();
    }
    /**
     * Retrieve the child model for a bound value.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolve_soft_deletable_child_route_binding($child_type, $value, $field)
    {
        return $this->resolve_child_route_binding_query($child_type, $value, $field)->with_trashed()->first();
    }
    /**
     * Retrieve the child model query for a bound value.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model, $this, *>
     */
    protected function resolve_child_route_binding_query($child_type, $value, $field)
    {
        $relationship = $this->{$this->child_route_binding_relationship_name($child_type)}();
        $field = $field ?: $relationship->get_related()->get_route_key_name();
        if ($relationship instanceof Has_Many_Through || $relationship instanceof Belongs_To_Many) {
            $field = $relationship->get_related()->qualify_column($field);
        }
        return $relationship instanceof Model ? $relationship->resolve_route_binding_query($relationship, $value, $field) : $relationship->get_related()->resolve_route_binding_query($relationship, $value, $field);
    }
    /**
     * Retrieve the child route model binding relationship name for the given child type.
     *
     * @return string
     */
    protected function child_route_binding_relationship_name(string $child_type)
    {
        return Str::plural(Str::camel($child_type));
    }
    /**
     * Retrieve the model for a bound value.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Contracts\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function resolve_route_binding_query($query, $value, $field = null)
    {
        return $query->where($field ?? $this->get_route_key_name(), $value);
    }
    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function get_foreign_key()
    {
        return Str::snake(class_basename($this)) . '_' . $this->get_key_name();
    }
    /**
     * Get the number of models to return per page.
     *
     * @return int
     */
    public function get_per_page()
    {
        return $this->per_page;
    }
    /**
     * Set the number of models to return per page.
     *
     * @param  int  $perPage
     * @return $this
     */
    public function set_per_page($per_page)
    {
        $this->per_page = $per_page;
        return $this;
    }
    /**
     * Determine if the model is soft deletable.
     */
    public static function is_soft_deletable(): bool
    {
        return static::$is_soft_deletable[static::class] ??= in_array(Soft_Deletes::class, class_uses_recursive(static::class));
    }
    /**
     * Determine if the model is prunable.
     */
    protected function is_prunable(): bool
    {
        return self::$is_prunable[static::class] ??= in_array(Prunable::class, class_uses_recursive(static::class)) || static::is_mass_prunable();
    }
    /**
     * Determine if the model is mass prunable.
     */
    protected function is_mass_prunable(): bool
    {
        return self::$is_mass_prunable[static::class] ??= in_array(Mass_Prunable::class, class_uses_recursive(static::class));
    }
    /**
     * Determine if lazy loading is disabled.
     *
     * @return bool
     */
    public static function prevents_lazy_loading()
    {
        return static::$models_should_prevent_lazy_loading;
    }
    /**
     * Determine if relationships are being automatically eager loaded when accessed.
     *
     * @return bool
     */
    public static function is_automatically_eager_loading_relationships()
    {
        return static::$models_should_automatically_eager_load_relationships;
    }
    /**
     * Determine if discarding guarded attribute fills is disabled.
     *
     * @return bool
     */
    public static function prevents_silently_discarding_attributes()
    {
        return static::$models_should_prevent_silently_discarding_attributes;
    }
    /**
     * Determine if accessing missing attributes is disabled.
     *
     * @return bool
     */
    public static function prevents_accessing_missing_attributes()
    {
        return static::$models_should_prevent_accessing_missing_attributes;
    }
    /**
     * Get the broadcast channel route definition that is associated with the given entity.
     *
     * @return string
     */
    public function broadcast_channel_route()
    {
        return str_replace('\\', '.', static::class) . '.{' . Str::camel(class_basename($this)) . '}';
    }
    /**
     * Get the broadcast channel name that is associated with the given entity.
     *
     * @return string
     */
    public function broadcast_channel()
    {
        return str_replace('\\', '.', static::class) . '.' . $this->get_key();
    }
    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key): mixed
    {
        return $this->get_attribute($key);
    }
    /**
     * Dynamically set attributes on the model.
     *
     * @return void
     */
    public function __set(string $key, mixed $value)
    {
        $this->set_attribute($key, $value);
    }
    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     */
    public function offsetExists($offset): bool
    {
        $should_prevent = static::$models_should_prevent_accessing_missing_attributes;
        static::$models_should_prevent_accessing_missing_attributes = false;
        try {
            return !is_null($this->get_attribute($offset));
        } finally {
            static::$models_should_prevent_accessing_missing_attributes = $should_prevent;
        }
    }
    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->get_attribute($offset);
    }
    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->set_attribute($offset, $value);
    }
    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset], $this->relations[$offset], $this->attribute_cast_cache[$offset], $this->class_cast_cache[$offset]);
    }
    /**
     * Determine if an attribute or relation exists on the model.
     *
     * @return bool
     */
    public function __isset(string $key)
    {
        return $this->offsetExists($key);
    }
    /**
     * Unset an attribute on the model.
     *
     * @return void
     */
    public function __unset(string $key)
    {
        $this->offsetUnset($key);
    }
    /**
     * Handle dynamic method calls into the model.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (in_array($method, ['increment', 'decrement', 'incrementQuietly', 'decrementQuietly'])) {
            return $this->{$method}(...$parameters);
        }
        if ($resolver = $this->relation_resolver(static::class, $method)) {
            return $resolver($this);
        }
        if (Str::starts_with($method, 'through') && method_exists($this, $relation_method = (new Support_Stringable($method))->after('through')->lcfirst()->to_string())) {
            return $this->through($relation_method);
        }
        return $this->forward_call_to($this->new_query(), $method, $parameters);
    }
    /**
     * Handle dynamic static method calls into the model.
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (static::is_scope_method_with_attribute($method)) {
            return static::query()->{$method}(...$parameters);
        }
        return (new static())->{$method}(...$parameters);
    }
    /**
     * Convert the model to its string representation.
     */
    public function __toString(): string
    {
        return $this->escape_when_casting_to_string ? e($this->to_json()) : $this->to_json();
    }
    /**
     * Indicate that the object's string representation should be escaped when __toString is invoked.
     *
     * @param  bool  $escape
     * @return $this
     */
    public function escape_when_casting_to_string($escape = true)
    {
        $this->escape_when_casting_to_string = $escape;
        return $this;
    }
    /**
     * Prepare the object for serialization.
     *
     * @return array
     */
    public function __sleep()
    {
        $this->merge_attributes_from_cached_casts();
        $this->class_cast_cache = [];
        $this->attribute_cast_cache = [];
        $this->relation_autoload_callback = null;
        $this->relation_autoload_context = null;
        $keys = get_object_vars($this);
        return array_keys($keys);
    }
    /**
     * When a model is being unserialized, check if it needs to be booted.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->boot_if_not_booted();
        $this->initialize_traits();
        if (static::is_automatically_eager_loading_relationships()) {
            $this->with_relationship_autoloading();
        }
    }
}