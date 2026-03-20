<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as BaseCollection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Spl_File_Object;
class Model_Inspector
{
    /**
     * The methods that can be called in a model to indicate a relation.
     *
     * @var list<string>
     */
    protected $relation_methods = ['hasMany', 'hasManyThrough', 'hasOneThrough', 'belongsToMany', 'hasOne', 'belongsTo', 'morphOne', 'morphTo', 'morphMany', 'morphToMany', 'morphedByMany'];
    /**
     * Create a new model inspector instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app  The Laravel application instance.
     */
    public function __construct(protected Application $app)
    {
    }
    /**
     * Extract model details for the given model.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>|string  $model
     * @param  string|null  $connection
     * @return array{"class": class-string<\Illuminate\Database\Eloquent\Model>, database: string, table: string, policy: class-string|null, attributes: \Illuminate\Support\Collection, relations: \Illuminate\Support\Collection, events: \Illuminate\Support\Collection, observers: \Illuminate\Support\Collection, collection: class-string<\Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>>, builder: class-string<\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>>, "resource": class-string<\Illuminate\Http\Resources\Json\JsonResource>|null}
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function inspect($model, $connection = null): array
    {
        $class = $this->qualify_model($model);
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $this->app->make($class);
        if ($connection !== null) {
            $model->set_connection($connection);
        }
        return ['class' => $model::class, 'database' => $model->get_connection()->get_name(), 'table' => $model->get_connection()->get_table_prefix() . $model->get_table(), 'policy' => $this->get_policy($model), 'attributes' => $this->get_attributes($model), 'relations' => $this->get_relations($model), 'events' => $this->get_events($model), 'observers' => $this->get_observers($model), 'collection' => $this->get_collected_by($model), 'builder' => $this->get_builder($model), 'resource' => $this->get_resource($model)];
    }
    /**
     * Get the column attributes for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function get_attributes($model): \Illuminate\Support\Collection
    {
        $connection = $model->get_connection();
        $schema = $connection->get_schema_builder();
        $table = $model->get_table();
        $columns = $schema->get_columns($table);
        $indexes = $schema->get_indexes($table);
        return (new Base_Collection($columns))->map(fn(array $column): array => ['name' => $column['name'], 'type' => $column['type'], 'increments' => $column['auto_increment'], 'nullable' => $column['nullable'], 'default' => $this->get_column_default($column, $model), 'unique' => $this->column_is_unique($column['name'], $indexes), 'fillable' => $model->is_fillable($column['name']), 'hidden' => $this->attribute_is_hidden($column['name'], $model), 'appended' => null, 'cast' => $this->get_cast_type($column['name'], $model)])->merge($this->get_virtual_attributes($model, $columns));
    }
    /**
     * Get the virtual (non-column) attributes for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array  $columns
     */
    protected function get_virtual_attributes($model, $columns): \Illuminate\Support\Collection
    {
        $class = new ReflectionClass($model);
        return (new Base_Collection($class->get_methods()))->reject(fn(ReflectionMethod $method): bool => $method->is_static() || $method->is_abstract() || $method->get_declaring_class()->get_name() === Model::class)->map_with_keys(function (ReflectionMethod $method) use ($model): array {
            if (preg_match('/^get(.+)Attribute$/', $method->get_name(), $matches) === 1) {
                return [Str::snake($matches[1]) => 'accessor'];
            }
            if ($model->has_attribute_mutator($method->get_name())) {
                return [Str::snake($method->get_name()) => 'attribute'];
            }
            return [];
        })->reject(fn($cast, $name) => (new Base_Collection($columns))->contains('name', $name))->map(fn($cast, $name): array => ['name' => $name, 'type' => null, 'increments' => false, 'nullable' => null, 'default' => null, 'unique' => null, 'fillable' => $model->is_fillable($name), 'hidden' => $this->attribute_is_hidden($name, $model), 'appended' => $model->has_appended($name), 'cast' => $cast])->values();
    }
    /**
     * Get the relations from the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    protected function get_relations($model): \Illuminate\Support\Collection
    {
        return (new Base_Collection(get_class_methods($model)))->map(fn($method): \ReflectionMethod => new ReflectionMethod($model, $method))->reject(fn(ReflectionMethod $method): bool => $method->is_static() || $method->is_abstract() || $method->get_declaring_class()->get_name() === Model::class || $method->get_number_of_parameters() > 0)->filter(function (ReflectionMethod $method) {
            if ($method->get_return_type() instanceof ReflectionNamedType && is_subclass_of($method->get_return_type()->get_name(), Relation::class)) {
                return true;
            }
            $file = new Spl_File_Object($method->get_file_name());
            $file->seek($method->get_start_line() - 1);
            $code = '';
            while ($file->key() < $method->get_end_line()) {
                $code .= trim($file->current());
                $file->next();
            }
            return (new Base_Collection($this->relation_methods))->contains(fn($relation_method): bool => str_contains($code, '$this->' . $relation_method . '('));
        })->map(function (ReflectionMethod $method) use ($model): ?array {
            $relation = $method->invoke($model);
            if (!$relation instanceof Relation) {
                return null;
            }
            return ['name' => $method->get_name(), 'type' => Str::after_last($relation::class, '\\'), 'related' => $relation->get_related()::class];
        })->filter()->values();
    }
    /**
     * Get the first policy associated with this model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return string|null
     */
    protected function get_policy($model)
    {
        $policy = Gate::get_policy_for($model::class);
        return $policy ? $policy::class : null;
    }
    /**
     * Get the events that the model dispatches.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    protected function get_events($model): \Illuminate\Support\Collection
    {
        return (new Base_Collection($model->dispatches_events()))->map(fn(string $class, string $event): array => ['event' => $event, 'class' => $class])->values();
    }
    /**
     * Get the observers watching this model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function get_observers($model): \Illuminate\Support\Collection
    {
        $listeners = $this->app->make('events')->get_raw_listeners();
        // Get the Eloquent observers for this model...
        $listeners = array_filter($listeners, fn($v, $key): bool => Str::starts_with($key, 'eloquent.') && Str::ends_with($key, $model::class), ARRAY_FILTER_USE_BOTH);
        // Format listeners Eloquent verb => Observer methods...
        $extract_verb = function ($key): string {
            preg_match('/eloquent.([a-zA-Z]+)\: /', $key, $matches);
            return $matches[1] ?? '?';
        };
        $formatted = [];
        foreach ($listeners as $key => $observer_methods) {
            $formatted[] = ['event' => $extract_verb($key), 'observer' => array_map(fn($obs): string => is_string($obs) ? $obs : 'Closure', $observer_methods)];
        }
        return new Base_Collection($formatted);
    }
    /**
     * Get the collection class being used by the model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return class-string<\Illuminate\Database\Eloquent\Collection>
     */
    protected function get_collected_by($model): string
    {
        return $model->new_collection()::class;
    }
    /**
     * Get the builder class being used by the model.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $model
     * @return class-string<\Illuminate\Database\Eloquent\Builder<TModel>>
     */
    protected function get_builder($model): string
    {
        return $model->new_query()::class;
    }
    /**
     * Get the class used for JSON response transforming.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Http\Resources\Json\JsonResource|null
     */
    protected function get_resource($model)
    {
        return rescue(static fn() => $model->to_resource()::class, null, false);
    }
    /**
     * Qualify the given model class base name.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     * @see \Illuminate\Console\GeneratorCommand
     */
    protected function qualify_model(string $model)
    {
        if (str_contains($model, '\\') && class_exists($model)) {
            return $model;
        }
        $model = ltrim($model, '\/');
        $model = str_replace('/', '\\', $model);
        $root_namespace = $this->app->get_namespace();
        if (Str::starts_with($model, $root_namespace)) {
            return $model;
        }
        return is_dir(app_path('Models')) ? $root_namespace . 'Models\\' . $model : $root_namespace . $model;
    }
    /**
     * Get the cast type for the given column.
     *
     * @param  string  $column
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return string|null
     */
    protected function get_cast_type($column, $model)
    {
        if ($model->has_get_mutator($column) || $model->has_set_mutator($column)) {
            return 'accessor';
        }
        if ($model->has_attribute_mutator($column)) {
            return 'attribute';
        }
        return $this->get_casts_with_dates($model)->get($column) ?? null;
    }
    /**
     * Get the model casts, including any date casts.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    protected function get_casts_with_dates($model): \Illuminate\Support\Collection
    {
        return (new Base_Collection($model->get_dates()))->filter()->flip()->map(fn(): string => 'datetime')->merge($model->get_casts());
    }
    /**
     * Determine if the given attribute is hidden.
     *
     * @param  string  $attribute
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function attribute_is_hidden($attribute, $model)
    {
        if (count($model->get_hidden()) > 0) {
            return in_array($attribute, $model->get_hidden());
        }
        if (count($model->get_visible()) > 0) {
            return !in_array($attribute, $model->get_visible());
        }
        return false;
    }
    /**
     * Get the default value for the given column.
     *
     * @param  array<string, mixed>  $column
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return mixed
     */
    protected function get_column_default(array $column, $model)
    {
        $attribute_default = $model->get_attributes()[$column['name']] ?? null;
        return enum_value($attribute_default) ?? $column['default'];
    }
    /**
     * Determine if the given attribute is unique.
     *
     * @param  string  $column
     * @param  array  $indexes
     * @return bool
     */
    protected function column_is_unique($column, $indexes)
    {
        return (new Base_Collection($indexes))->contains(fn($index): bool => count($index['columns']) === 1 && $index['columns'][0] === $column && $index['unique']);
    }
}