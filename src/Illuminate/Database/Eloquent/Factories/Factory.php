<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Factories;

use Closure;
use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Forwards_Calls;
use Illuminate\Support\Traits\Macroable;
use Throwable;
use Unit_Enum;
/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @method $this trashed()
 */
abstract class Factory
{
    use Conditionable, Forwards_Calls, Macroable {
        __call as macroCall;
    }
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model;
    /**
     * The state transformations that will be applied to the model.
     */
    protected \Illuminate\Support\Collection $states;
    /**
     * The parent relationships that will be applied to the model.
     */
    protected \Illuminate\Support\Collection $has;
    /**
     * The child relationships that will be applied to the model.
     */
    protected \Illuminate\Support\Collection $for;
    /**
     * The model instances to always use when creating relationships.
     */
    protected \Illuminate\Support\Collection $recycle;
    /**
     * The "after making" callbacks that will be applied to the model.
     */
    protected \Illuminate\Support\Collection $after_making;
    /**
     * The "after creating" callbacks that will be applied to the model.
     */
    protected \Illuminate\Support\Collection $after_creating;
    /**
     * Whether relationships should not be automatically created.
     *
     * @var bool
     */
    protected $expand_relationships = true;
    /**
     * The current Faker instance.
     *
     * @var \Faker\Generator
     */
    protected $faker;
    /**
     * The default namespace where factories reside.
     *
     * @var string
     */
    public static $namespace = 'Database\Factories\\';
    /**
     * @deprecated use $modelNameResolvers
     *
     * @var callable(self): class-string<TModel>
     */
    protected static $model_name_resolver;
    /**
     * The default model name resolvers.
     *
     * @var array<class-string, callable(self): class-string<TModel>>
     */
    protected static $model_name_resolvers = [];
    /**
     * The factory name resolver.
     *
     * @var callable
     */
    protected static $factory_name_resolver;
    /**
     * Whether to expand relationships by default.
     *
     * @var bool
     */
    protected static $expand_relationships_by_default = true;
    /**
     * Create a new factory instance.
     *
     * @param  int|null  $count
     * @param  \UnitEnum|string|null  $connection
     */
    public function __construct(
        /**
         * The number of models that should be generated.
         */
        protected $count = null,
        ?Collection $states = null,
        ?Collection $has = null,
        ?Collection $for = null,
        ?Collection $after_making = null,
        ?Collection $after_creating = null,
        /**
         * The name of the database connection that will be used to create the models.
         */
        protected $connection = null,
        ?Collection $recycle = null,
        ?bool $expand_relationships = null,
        /**
         * The relationships that should not be automatically created.
         */
        protected array $exclude_relationships = []
    )
    {
        $this->states = $states ?? new Collection();
        $this->has = $has ?? new Collection();
        $this->for = $for ?? new Collection();
        $this->after_making = $after_making ?? new Collection();
        $this->after_creating = $after_creating ?? new Collection();
        $this->recycle = $recycle ?? new Collection();
        $this->faker = $this->with_faker();
        $this->expand_relationships = $expand_relationships ?? self::$expand_relationships_by_default;
    }
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    abstract public function definition();
    /**
     * Get a new factory instance for the given attributes.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return static
     */
    public static function new($attributes = [])
    {
        return (new static())->state($attributes)->configure();
    }
    /**
     * Get a new factory instance for the given number of models.
     *
     * @return static
     */
    public static function times(int $count)
    {
        return static::new()->count($count);
    }
    /**
     * Configure the factory.
     *
     * @return static
     */
    public function configure()
    {
        return $this;
    }
    /**
     * Get the raw attributes generated by the factory.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return array<int|string, mixed>
     */
    public function raw($attributes = [], ?Model $parent = null)
    {
        if ($this->count === null) {
            return $this->state($attributes)->get_expanded_attributes($parent);
        }
        return array_map(fn() => $this->state($attributes)->get_expanded_attributes($parent), range(1, $this->count));
    }
    /**
     * Create a single model and persist it to the database.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return TModel
     */
    public function create_one($attributes = [])
    {
        return $this->count(null)->create($attributes);
    }
    /**
     * Create a single model and persist it to the database without dispatching any model events.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return TModel
     */
    public function create_one_quietly($attributes = [])
    {
        return $this->count(null)->create_quietly($attributes);
    }
    /**
     * Create a collection of models and persist them to the database.
     *
     * @param  int|null|iterable<int, array<string, mixed>>  $records
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function create_many(int|iterable|null $records = null)
    {
        $records ??= $this->count ?? 1;
        $this->count = null;
        if (is_numeric($records)) {
            $records = array_fill(0, $records, []);
        }
        return new Eloquent_Collection((new Collection($records))->map(fn($record) => $this->state($record)->create()));
    }
    /**
     * Create a collection of models and persist them to the database without dispatching any model events.
     *
     * @param  int|null|iterable<int, array<string, mixed>>  $records
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function create_many_quietly(int|iterable|null $records = null)
    {
        return Model::without_events(fn() => $this->create_many($records));
    }
    /**
     * Create a collection of models and persist them to the database.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>|TModel
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        if (!empty($attributes)) {
            return $this->state($attributes)->create([], $parent);
        }
        $results = $this->make($attributes, $parent);
        if ($results instanceof Model) {
            $this->store(new Collection([$results]));
            $this->call_after_creating(new Collection([$results]), $parent);
        } else {
            $this->store($results);
            $this->call_after_creating($results, $parent);
        }
        return $results;
    }
    /**
     * Create a collection of models and persist them to the database without dispatching any model events.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>|TModel
     */
    public function create_quietly($attributes = [], ?Model $parent = null)
    {
        return Model::without_events(fn() => $this->create($attributes, $parent));
    }
    /**
     * Create a callback that persists a model in the database when invoked.
     *
     * @param  array<string, mixed>  $attributes
     * @return \Closure(): (\Illuminate\Database\Eloquent\Collection<int, TModel>|TModel)
     */
    public function lazy(array $attributes = [], ?Model $parent = null)
    {
        return fn() => $this->create($attributes, $parent);
    }
    /**
     * Set the connection name on the results and store them.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $results
     * @return void
     */
    protected function store(Collection $results)
    {
        $results->each(function (\Illuminate\Database\Eloquent\Model $model): void {
            if (!isset($this->connection)) {
                $model->set_connection($model->new_query_without_scopes()->get_connection()->get_name());
            }
            $model->save();
            foreach ($model->get_relations() as $name => $items) {
                if ($items instanceof Enumerable && $items->is_empty()) {
                    $model->unset_relation($name);
                }
            }
            $this->create_children($model);
        });
    }
    /**
     * Create the children for the given model.
     *
     * @return void
     */
    protected function create_children(Model $model)
    {
        Model::unguarded(function () use ($model): void {
            $this->has->each(function ($has) use ($model): void {
                $has->recycle($this->recycle)->create_for($model);
            });
        });
    }
    /**
     * Make a single instance of the model.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return TModel
     */
    public function make_one($attributes = [])
    {
        return $this->count(null)->make($attributes);
    }
    /**
     * Create a collection of models.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>|TModel
     */
    public function make($attributes = [], ?Model $parent = null)
    {
        $auto_eager_loading_enabled = Model::is_automatically_eager_loading_relationships();
        if ($auto_eager_loading_enabled) {
            Model::automatically_eager_load_relationships(false);
        }
        try {
            if (!empty($attributes)) {
                return $this->state($attributes)->make([], $parent);
            }
            if ($this->count === null) {
                return tap($this->make_instance($parent), function ($instance): void {
                    $this->call_after_making(new Collection([$instance]));
                });
            }
            if ($this->count < 1) {
                return $this->new_model()->new_collection();
            }
            $instances = $this->new_model()->new_collection(array_map(fn() => $this->make_instance($parent), range(1, $this->count)));
            $this->call_after_making($instances);
            return $instances;
        } finally {
            Model::automatically_eager_load_relationships($auto_eager_loading_enabled);
        }
    }
    /**
     * Create a collection of models.
     *
     * @param  iterable<int, array<string, mixed>>|int|null  $records
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function make_many(iterable|int|null $records = null)
    {
        $records ??= $this->count ?? 1;
        $this->count = null;
        if (is_numeric($records)) {
            $records = array_fill(0, $records, []);
        }
        return new Eloquent_Collection((new Collection($records))->map(fn($record) => $this->state($record)->make()));
    }
    /**
     * Insert the model records in bulk. No model events are emitted.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function insert(array $attributes = [], ?Model $parent = null): void
    {
        $made = $this->make($attributes, $parent);
        $made_collection = $made instanceof Collection ? $made : $this->new_model()->new_collection([$made]);
        $model = $made_collection->first();
        if (isset($this->connection)) {
            $model->set_connection($this->connection);
        }
        $query = $model->new_query_without_scopes();
        $query->fill_and_insert($made_collection->without_appends()->set_hidden([])->map(static fn(Model $model) => $model->attributes_to_array())->all());
    }
    /**
     * Make an instance of the model with the given attributes.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function make_instance(?Model $parent)
    {
        return Model::unguarded(fn() => tap($this->new_model($this->get_expanded_attributes($parent)), function ($instance): void {
            if (isset($this->connection)) {
                $instance->set_connection($this->connection);
            }
        }));
    }
    /**
     * Get a raw attributes array for the model.
     *
     * @return mixed
     */
    protected function get_expanded_attributes(?Model $parent)
    {
        return $this->expand_attributes($this->get_raw_attributes($parent));
    }
    /**
     * Get the raw attributes for the model as an array.
     *
     * @return array
     */
    protected function get_raw_attributes(?Model $parent)
    {
        return $this->states->pipe(fn($states): \Illuminate\Support\Collection => $this->for->is_empty() ? $states : new Collection(array_merge([$this->parent_resolvers(...)], $states->all())))->reduce(function ($carry, $state) use ($parent): array {
            if ($state instanceof Closure) {
                $state = $state->bind_to($this);
            }
            return array_merge($carry, $state($carry, $parent));
        }, $this->definition());
    }
    /**
     * Create the parent relationship resolvers (as deferred Closures).
     *
     * @return array
     */
    protected function parent_resolvers()
    {
        return $this->for->map(fn(Belongs_To_Relationship $for) => $for->recycle($this->recycle)->attributes_for($this->new_model()))->collapse()->all();
    }
    /**
     * Expand all attributes to their underlying values.
     *
     * @return array
     */
    protected function expand_attributes(array $definition)
    {
        return (new Collection($definition))->map($evaluate_relations = function ($attribute, $key) {
            if (!$this->expand_relationships && $attribute instanceof self) {
                $attribute = null;
            } elseif ($attribute instanceof self && array_intersect([$attribute->model_name(), $key], $this->exclude_relationships)) {
                $attribute = null;
            } elseif ($attribute instanceof self) {
                $attribute = $this->get_random_recycled_model($attribute->model_name())?->get_key() ?? $attribute->recycle($this->recycle)->create()->get_key();
            } elseif ($attribute instanceof Model) {
                $attribute = $attribute->get_key();
            }
            return $attribute;
        })->map(function ($attribute, $key) use (&$definition, $evaluate_relations) {
            if (is_callable($attribute) && !is_string($attribute) && !is_array($attribute)) {
                $attribute = $attribute($definition);
            }
            $attribute = $evaluate_relations($attribute, $key);
            $definition[$key] = $attribute;
            return $attribute;
        })->all();
    }
    /**
     * Add a new state transformation to the model definition.
     *
     * @param  (callable(array<string, mixed>, Model|null): array<string, mixed>)|array<string, mixed>  $state
     * @return static
     */
    public function state($state)
    {
        return $this->new_instance(['states' => $this->states->concat([is_callable($state) ? $state : fn() => $state])]);
    }
    /**
     * Prepend a new state transformation to the model definition.
     *
     * @param  (callable(array<string, mixed>, Model|null): array<string, mixed>)|array<string, mixed>  $state
     * @return static
     */
    public function prepend_state($state)
    {
        return $this->new_instance(['states' => $this->states->prepend(is_callable($state) ? $state : fn() => $state)]);
    }
    /**
     * Set a single model attribute.
     *
     * @param  string|int  $key
     * @param  mixed  $value
     * @return static
     */
    public function set($key, $value)
    {
        return $this->state([$key => $value]);
    }
    /**
     * Add a new sequenced state transformation to the model definition.
     *
     * @param  mixed  ...$sequence
     * @return static
     */
    public function sequence(...$sequence)
    {
        return $this->state(new Sequence(...$sequence));
    }
    /**
     * Add a new sequenced state transformation to the model definition and update the pending creation count to the size of the sequence.
     *
     * @param  array  ...$sequence
     * @return static
     */
    public function for_each_sequence(...$sequence)
    {
        return $this->state(new Sequence(...$sequence))->count(count($sequence));
    }
    /**
     * Add a new cross joined sequenced state transformation to the model definition.
     *
     * @param  array  ...$sequence
     * @return static
     */
    public function cross_join_sequence(...$sequence)
    {
        return $this->state(new Cross_Join_Sequence(...$sequence));
    }
    /**
     * Define a child relationship for the model.
     *
     * @param  string|null  $relationship
     * @return static
     */
    public function has(self $factory, $relationship = null)
    {
        return $this->new_instance(['has' => $this->has->concat([new Relationship($factory, $relationship ?? $this->guess_relationship($factory->model_name()))])]);
    }
    /**
     * Attempt to guess the relationship name for a "has" relationship.
     *
     * @return string
     */
    protected function guess_relationship(string $related)
    {
        $guess = Str::camel(Str::plural(class_basename($related)));
        return method_exists($this->model_name(), $guess) ? $guess : Str::singular($guess);
    }
    /**
     * Define an attached relationship for the model.
     *
     * @param  \Illuminate\Database\Eloquent\Factories\Factory|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array  $factory
     * @param  (callable(): array<string, mixed>)|array<string, mixed>  $pivot
     * @param  string|null  $relationship
     * @return static
     */
    public function has_attached($factory, $pivot = [], $relationship = null)
    {
        return $this->new_instance(['has' => $this->has->concat([new Belongs_To_Many_Relationship($factory, $pivot, $relationship ?? Str::camel(Str::plural(class_basename($factory instanceof Factory ? $factory->model_name() : Collection::wrap($factory)->first()))))])]);
    }
    /**
     * Define a parent relationship for the model.
     *
     * @param  \Illuminate\Database\Eloquent\Factories\Factory|\Illuminate\Database\Eloquent\Model  $factory
     * @param  string|null  $relationship
     * @return static
     */
    public function for($factory, $relationship = null)
    {
        return $this->new_instance(['for' => $this->for->concat([new Belongs_To_Relationship($factory, $relationship ?? Str::camel(class_basename($factory instanceof Factory ? $factory->model_name() : $factory)))])]);
    }
    /**
     * Provide model instances to use instead of any nested factory calls when creating relationships.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Support\Collection|array  $model
     * @return static
     */
    public function recycle($model)
    {
        // Group provided models by the type and merge them into existing recycle collection
        return $this->new_instance(['recycle' => $this->recycle->flatten()->merge(Collection::wrap($model instanceof Model ? func_get_args() : $model)->flatten())->group_by(fn($model): string|false => $model::class)]);
    }
    /**
     * Retrieve a random model of a given type from previously provided models to recycle.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TClass>  $modelClassName
     * @return TClass|null
     */
    public function get_random_recycled_model($model_class_name)
    {
        return $this->recycle->get($model_class_name)?->random();
    }
    /**
     * Add a new "after making" callback to the model definition.
     *
     * @param  \Closure(TModel): mixed  $callback
     * @return static
     */
    public function after_making(Closure $callback)
    {
        return $this->new_instance(['afterMaking' => $this->after_making->concat([$callback])]);
    }
    /**
     * Add a new "after creating" callback to the model definition.
     *
     * @param  \Closure(TModel, \Illuminate\Database\Eloquent\Model|null): mixed  $callback
     * @return static
     */
    public function after_creating(Closure $callback)
    {
        return $this->new_instance(['afterCreating' => $this->after_creating->concat([$callback])]);
    }
    /**
     * Remove the "after making" callbacks from the factory.
     *
     * @return static
     */
    public function without_after_making()
    {
        return $this->new_instance(['afterMaking' => new Collection()]);
    }
    /**
     * Remove the "after creating" callbacks from the factory.
     *
     * @return static
     */
    public function without_after_creating()
    {
        return $this->new_instance(['afterCreating' => new Collection()]);
    }
    /**
     * Call the "after making" callbacks for the given model instances.
     *
     * @return void
     */
    protected function call_after_making(Collection $instances)
    {
        $instances->each(function ($model): void {
            $this->after_making->each(function ($callback) use ($model): void {
                $callback($model);
            });
        });
    }
    /**
     * Call the "after creating" callbacks for the given model instances.
     *
     * @return void
     */
    protected function call_after_creating(Collection $instances, ?Model $parent = null)
    {
        $instances->each(function ($model) use ($parent): void {
            $this->after_creating->each(function ($callback) use ($model, $parent): void {
                $callback($model, $parent);
            });
        });
    }
    /**
     * Specify how many models should be generated.
     *
     * @return static
     */
    public function count(?int $count)
    {
        return $this->new_instance(['count' => $count]);
    }
    /**
     * Indicate that related parent models should not be created.
     *
     * @param  array<string|class-string<Model>>  $parents
     * @return static
     */
    public function without_parents($parents = [])
    {
        return $this->new_instance(!$parents ? ['expandRelationships' => false] : ['excludeRelationships' => $parents]);
    }
    /**
     * Get the name of the database connection that is used to generate models.
     *
     * @return string
     */
    public function get_connection_name()
    {
        return enum_value($this->connection);
    }
    /**
     * Specify the database connection that should be used to generate models.
     *
     * @return static
     */
    public function connection(Unit_Enum|string|null $connection)
    {
        return $this->new_instance(['connection' => $connection]);
    }
    /**
     * Create a new instance of the factory builder with the given mutated properties.
     *
     * @return static
     */
    protected function new_instance(array $arguments = [])
    {
        return new static(...array_values(array_merge(['count' => $this->count, 'states' => $this->states, 'has' => $this->has, 'for' => $this->for, 'afterMaking' => $this->after_making, 'afterCreating' => $this->after_creating, 'connection' => $this->connection, 'recycle' => $this->recycle, 'expandRelationships' => $this->expand_relationships, 'excludeRelationships' => $this->exclude_relationships], $arguments)));
    }
    /**
     * Get a new model instance.
     *
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function new_model(array $attributes = [])
    {
        $model = $this->model_name();
        return new $model($attributes);
    }
    /**
     * Get the name of the model that is generated by the factory.
     *
     * @return class-string<TModel>
     */
    public function model_name()
    {
        if ($this->model !== null) {
            return $this->model;
        }
        $resolver = static::$model_name_resolvers[static::class] ?? static::$model_name_resolvers[self::class] ?? static::$model_name_resolver ?? function (self $factory): string {
            $namespaced_factory_basename = Str::replace_last('Factory', '', Str::replace_first(static::$namespace, '', $factory::class));
            $factory_basename = Str::replace_last('Factory', '', class_basename($factory));
            $app_namespace = static::app_namespace();
            return class_exists($app_namespace . 'Models\\' . $namespaced_factory_basename) ? $app_namespace . 'Models\\' . $namespaced_factory_basename : $app_namespace . $factory_basename;
        };
        return $resolver($this);
    }
    /**
     * Specify the callback that should be invoked to guess model names based on factory names.
     *
     * @param  callable(self): class-string<TModel>  $callback
     */
    public static function guess_model_names_using(callable $callback): void
    {
        static::$model_name_resolvers[static::class] = $callback;
    }
    /**
     * Specify the default namespace that contains the application's model factories.
     */
    public static function use_namespace(string $namespace): void
    {
        static::$namespace = $namespace;
    }
    /**
     * Get a new factory instance for the given model name.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TClass>  $modelName
     * @return \Illuminate\Database\Eloquent\Factories\Factory<TClass>
     */
    public static function factory_for_model(string $model_name)
    {
        $factory = static::resolve_factory_name($model_name);
        return $factory::new();
    }
    /**
     * Specify the callback that should be invoked to guess factory names based on dynamic relationship names.
     *
     * @param  callable(class-string<\Illuminate\Database\Eloquent\Model>): class-string<\Illuminate\Database\Eloquent\Factories\Factory>  $callback
     */
    public static function guess_factory_names_using(callable $callback): void
    {
        static::$factory_name_resolver = $callback;
    }
    /**
     * Specify that relationships should create parent relationships by default.
     */
    public static function expand_relationships_by_default(): void
    {
        static::$expand_relationships_by_default = true;
    }
    /**
     * Specify that relationships should not create parent relationships by default.
     */
    public static function dont_expand_relationships_by_default(): void
    {
        static::$expand_relationships_by_default = false;
    }
    /**
     * Get a new Faker instance.
     *
     * @return \Faker\Generator|null
     */
    protected function with_faker()
    {
        if (!class_exists(Generator::class)) {
            return;
        }
        return Container::get_instance()->make(Generator::class);
    }
    /**
     * Get the factory name for the given model name.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TClass>  $modelName
     * @return class-string<\Illuminate\Database\Eloquent\Factories\Factory<TClass>>
     */
    public static function resolve_factory_name(string $model_name)
    {
        $resolver = static::$factory_name_resolver ?? function (string $model_name): string {
            $app_namespace = static::app_namespace();
            $model_name = Str::starts_with($model_name, $app_namespace . 'Models\\') ? Str::after($model_name, $app_namespace . 'Models\\') : Str::after($model_name, $app_namespace);
            return static::$namespace . $model_name . 'Factory';
        };
        return $resolver($model_name);
    }
    /**
     * Get the application namespace for the application.
     *
     * @return string
     */
    protected static function app_namespace()
    {
        try {
            return Container::get_instance()->make(Application::class)->get_namespace();
        } catch (Throwable) {
            return 'App\\';
        }
    }
    /**
     * Flush the factory's global state.
     */
    public static function flush_state(): void
    {
        static::$model_name_resolver = null;
        static::$model_name_resolvers = [];
        static::$factory_name_resolver = null;
        static::$namespace = 'Database\Factories\\';
        static::$expand_relationships_by_default = true;
    }
    /**
     * Proxy dynamic factory methods onto their proper methods.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        if ($method === 'trashed' && $this->model_name()::is_soft_deletable()) {
            return $this->state([$this->new_model()->get_deleted_at_column() => $parameters[0] ?? Carbon::now()->sub_day()]);
        }
        if (!Str::starts_with($method, ['for', 'has'])) {
            static::throw_bad_method_call_exception($method);
        }
        $relationship = Str::camel(Str::substr($method, 3));
        $related_model = $this->new_model()->{$relationship}()->get_related()::class;
        if (method_exists($related_model, 'newFactory')) {
            $factory = $related_model::new_factory() ?? static::factory_for_model($related_model);
        } else {
            $factory = static::factory_for_model($related_model);
        }
        if (str_starts_with($method, 'for')) {
            return $this->for($factory->state($parameters[0] ?? []), $relationship);
        }
        if (str_starts_with($method, 'has')) {
            return $this->has($factory->count(is_numeric($parameters[0] ?? null) ? $parameters[0] : 1)->state(is_callable($parameters[0] ?? null) || is_array($parameters[0] ?? null) ? $parameters[0] : $parameters[1] ?? []), $relationship);
        }
    }
}