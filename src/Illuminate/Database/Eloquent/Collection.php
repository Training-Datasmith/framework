<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Contracts\Queue\Queueable_Collection;
use Illuminate\Contracts\Queue\Queueable_Entity;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Dictionary;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use LogicException;
/**
 * @template TKey of array-key
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Support\Collection<TKey, TModel>
 */
class Collection extends Base_Collection implements Queueable_Collection
{
    use Interacts_With_Dictionary;
    /**
     * Find a model in the collection by key.
     *
     * @template TFindDefault
     *
     * @param  mixed  $key
     * @param  TFindDefault  $default
     * @return ($key is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? static : TModel|TFindDefault)
     */
    public function find($key, $default = null)
    {
        if ($key instanceof Model) {
            $key = $key->get_key();
        }
        if ($key instanceof Arrayable) {
            $key = $key->to_array();
        }
        if (is_array($key)) {
            if ($this->is_empty()) {
                return new static();
            }
            return $this->where_in($this->first()->get_key_name(), $key);
        }
        return Arr::first($this->items, fn($model): bool => $model->get_key() == $key, $default);
    }
    /**
     * Find a model in the collection by key or throw an exception.
     *
     * @param  mixed  $key
     * @return TModel
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find_or_fail($key)
    {
        $result = $this->find($key);
        if (is_array($key) && count($result) === count(array_unique($key))) {
            return $result;
        }
        if (!is_array($key) && !is_null($result)) {
            return $result;
        }
        $exception = new Model_Not_Found_Exception();
        if (!$model = head($this->items)) {
            throw $exception;
        }
        $ids = is_array($key) ? array_diff($key, $result->model_keys()) : $key;
        $exception->set_model($model::class, $ids);
        throw $exception;
    }
    /**
     * Load a set of relationships onto the collection.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @return $this
     */
    public function load($relations): static
    {
        if ($this->is_not_empty()) {
            if (is_string($relations)) {
                $relations = func_get_args();
            }
            $query = $this->first()->new_query_without_relationships()->with($relations);
            $this->items = $query->eager_load_relations($this->items);
        }
        return $this;
    }
    /**
     * Load a set of aggregations over relationship's column onto the collection.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @param  string  $column
     * @param  string|null  $function
     * @return $this
     */
    public function load_aggregate($relations, $column, $function = null): static
    {
        if ($this->is_empty()) {
            return $this;
        }
        $models = $this->first()->new_model_query()->where_key($this->model_keys())->select($this->first()->get_key_name())->with_aggregate($relations, $column, $function)->get()->key_by($this->first()->get_key_name());
        $attributes = Arr::except(array_keys($models->first()->get_attributes()), $models->first()->get_key_name());
        $this->each(function ($model) use ($models, $attributes): void {
            $extra_attributes = Arr::only($models->get($model->get_key())->get_attributes(), $attributes);
            $model->force_fill($extra_attributes)->sync_original_attributes($attributes)->merge_casts($models->get($model->get_key())->get_casts());
        });
        return $this;
    }
    /**
     * Load a set of relationship counts onto the collection.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @return $this
     */
    public function load_count($relations): static
    {
        return $this->load_aggregate($relations, '*', 'count');
    }
    /**
     * Load a set of relationship's max column values onto the collection.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @param  string  $column
     * @return $this
     */
    public function load_max($relations, $column): static
    {
        return $this->load_aggregate($relations, $column, 'max');
    }
    /**
     * Load a set of relationship's min column values onto the collection.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @param  string  $column
     * @return $this
     */
    public function load_min($relations, $column): static
    {
        return $this->load_aggregate($relations, $column, 'min');
    }
    /**
     * Load a set of relationship's column summations onto the collection.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @param  string  $column
     * @return $this
     */
    public function load_sum($relations, $column): static
    {
        return $this->load_aggregate($relations, $column, 'sum');
    }
    /**
     * Load a set of relationship's average column values onto the collection.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @param  string  $column
     * @return $this
     */
    public function load_avg($relations, $column): static
    {
        return $this->load_aggregate($relations, $column, 'avg');
    }
    /**
     * Load a set of related existences onto the collection.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @return $this
     */
    public function load_exists($relations): static
    {
        return $this->load_aggregate($relations, '*', 'exists');
    }
    /**
     * Load a set of relationships onto the collection if they are not already eager loaded.
     *
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>|string  $relations
     * @return $this
     */
    public function load_missing($relations): static
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }
        if ($this->is_not_empty()) {
            $query = $this->first()->new_query_without_relationships()->with($relations);
            foreach ($query->get_eager_loads() as $key => $value) {
                $segments = explode('.', explode(':', (string) $key)[0]);
                if (str_contains((string) $key, ':')) {
                    $segments[count($segments) - 1] .= ':' . explode(':', (string) $key)[1];
                }
                $path = [];
                foreach ($segments as $segment) {
                    $path[] = [$segment => $segment];
                }
                if (is_callable($value)) {
                    $path[count($segments) - 1][array_last($segments)] = $value;
                }
                $this->load_missing_relation($this, $path);
            }
        }
        return $this;
    }
    /**
     * Load a relationship path for models of the given type if it is not already eager loaded.
     *
     * @param  array<int, <string, class-string>>  $tuples
     */
    public function load_missing_relationship_chain(array $tuples): void
    {
        [$relation, $class] = array_shift($tuples);
        $this->filter(fn($model): bool => !is_null($model) && !$model->relation_loaded($relation) && $model::class === $class)->load($relation);
        if (empty($tuples)) {
            return;
        }
        $models = $this->pluck($relation)->where_not_null();
        if ($models->first() instanceof Base_Collection) {
            $models = $models->collapse();
        }
        (new static($models))->load_missing_relationship_chain($tuples);
    }
    /**
     * Load a relationship path if it is not already eager loaded.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TModel>  $models
     * @return void
     */
    protected function load_missing_relation(self $models, array $path)
    {
        $relation = array_shift($path);
        $name = explode(':', (string) key($relation))[0];
        if (is_string(reset($relation))) {
            $relation = reset($relation);
        }
        $models->filter(fn($model): bool => !is_null($model) && !$model->relation_loaded($name))->load($relation);
        if (empty($path)) {
            return;
        }
        $models = $models->pluck($name)->filter();
        if ($models->first() instanceof Base_Collection) {
            $models = $models->collapse();
        }
        $this->load_missing_relation(new static($models), $path);
    }
    /**
     * Load a set of relationships onto the mixed relationship collection.
     *
     * @param  string  $relation
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>  $relations
     * @return $this
     */
    public function load_morph($relation, $relations): static
    {
        $this->pluck($relation)->filter()->group_by(fn($model): string|false => $model::class)->each(fn($models, $class_name): static => static::make($models)->load($relations[$class_name] ?? []));
        return $this;
    }
    /**
     * Load a set of relationship counts onto the mixed relationship collection.
     *
     * @param  string  $relation
     * @param  array<array-key, array|(callable(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|string>  $relations
     * @return $this
     */
    public function load_morph_count($relation, $relations): static
    {
        $this->pluck($relation)->filter()->group_by(fn($model): string|false => $model::class)->each(fn($models, $class_name) => static::make($models)->load_count($relations[$class_name] ?? []));
        return $this;
    }
    /**
     * Determine if a key exists in the collection.
     *
     * @param  (callable(TModel, TKey): bool)|TModel|string|int  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (func_num_args() > 1 || $this->use_as_callable($key)) {
            return parent::contains(...func_get_args());
        }
        if ($key instanceof Model) {
            return parent::contains(fn($model) => $model->is($key));
        }
        return parent::contains(fn($model): bool => $model->get_key() == $key);
    }
    /**
     * Determine if a key does not exist in the collection.
     *
     * @param  (callable(TModel, TKey): bool)|TModel|string|int  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     */
    public function doesnt_contain($key, $operator = null, $value = null): bool
    {
        return !$this->contains(...func_get_args());
    }
    /**
     * Get the array of primary keys.
     *
     * @return array<int, array-key>
     */
    public function model_keys(): array
    {
        return array_map(fn(\Illuminate\Database\Eloquent\Model $model) => $model->get_key(), $this->items);
    }
    /**
     * Merge the collection with the given items.
     *
     * @param  iterable<array-key, TModel>  $items
     */
    public function merge($items): static
    {
        $dictionary = $this->get_dictionary();
        foreach ($items as $item) {
            $key = $this->get_dictionary_key($item->get_key());
            if ($key !== null) {
                $dictionary[$key] = $item;
            }
        }
        return new static(array_values($dictionary));
    }
    /**
     * Run a map over each of the items.
     *
     * @template TMapValue
     *
     * @param  callable(TModel, TKey): TMapValue  $callback
     * @return \Illuminate\Support\Collection<TKey, TMapValue>|static<TKey, TMapValue>
     */
    public function map(callable $callback): static
    {
        $result = parent::map($callback);
        return $result->contains(fn($item): false => !$item instanceof Model) ? $result->to_base() : $result;
    }
    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key / value pair.
     *
     * @template TMapWithKeysKey of array-key
     * @template TMapWithKeysValue
     *
     * @param  callable(TModel, TKey): array<TMapWithKeysKey, TMapWithKeysValue>  $callback
     * @return \Illuminate\Support\Collection<TMapWithKeysKey, TMapWithKeysValue>|static<TMapWithKeysKey, TMapWithKeysValue>
     */
    public function map_with_keys(callable $callback): static
    {
        $result = parent::map_with_keys($callback);
        return $result->contains(fn($item): false => !$item instanceof Model) ? $result->to_base() : $result;
    }
    /**
     * Reload a fresh model instance from the database for all the entities.
     *
     * @param  array<array-key, string>|string  $with
     */
    public function fresh($with = []): static
    {
        if ($this->is_empty()) {
            return new static();
        }
        $model = $this->first();
        $fresh_models = $model->new_query_without_scopes()->with(is_string($with) ? func_get_args() : $with)->where_in($model->get_key_name(), $this->model_keys())->get()->get_dictionary();
        return $this->filter(fn($model): bool => $model->exists && isset($fresh_models[$model->get_key()]))->map(fn($model) => $fresh_models[$model->get_key()]);
    }
    /**
     * Diff the collection with the given items.
     *
     * @param  iterable<array-key, TModel>  $items
     */
    public function diff($items): static
    {
        $diff = new static();
        $dictionary = $this->get_dictionary($items);
        foreach ($this->items as $item) {
            $key = $this->get_dictionary_key($item->get_key());
            if ($key === null || !isset($dictionary[$key])) {
                $diff->add($item);
            }
        }
        return $diff;
    }
    /**
     * Intersect the collection with the given items.
     *
     * @param  iterable<array-key, TModel>  $items
     */
    public function intersect($items): static
    {
        $intersect = new static();
        if (empty($items)) {
            return $intersect;
        }
        $dictionary = $this->get_dictionary($items);
        foreach ($this->items as $item) {
            $key = $this->get_dictionary_key($item->get_key());
            if ($key !== null && isset($dictionary[$key])) {
                $intersect->add($item);
            }
        }
        return $intersect;
    }
    /**
     * Return only unique items from the collection.
     *
     * @param  (callable(TModel, TKey): mixed)|string|null  $key
     * @param  bool  $strict
     * @return static
     */
    public function unique($key = null, $strict = false)
    {
        if (!is_null($key)) {
            return parent::unique($key, $strict);
        }
        return new static(array_values($this->get_dictionary()));
    }
    /**
     * Returns only the models from the collection with the specified keys.
     *
     * @param  array<array-key, mixed>|null  $keys
     */
    public function only($keys): static
    {
        if (is_null($keys)) {
            return new static($this->items);
        }
        $dictionary = Arr::only($this->get_dictionary(), array_map($this->get_dictionary_key(...), (array) $keys));
        return new static(array_values($dictionary));
    }
    /**
     * Returns all models in the collection except the models with specified keys.
     *
     * @param  array<array-key, mixed>|null  $keys
     */
    public function except($keys): static
    {
        if (is_null($keys)) {
            return new static($this->items);
        }
        $dictionary = Arr::except($this->get_dictionary(), array_map($this->get_dictionary_key(...), (array) $keys));
        return new static(array_values($dictionary));
    }
    /**
     * Make the given, typically visible, attributes hidden across the entire collection.
     *
     * @param  array<array-key, string>|string  $attributes
     * @return $this
     */
    public function make_hidden($attributes): \Illuminate\Database\Eloquent\Model
    {
        return $this->each->make_hidden($attributes);
    }
    /**
     * Merge the given, typically visible, attributes hidden across the entire collection.
     *
     * @param  array<array-key, string>|string  $attributes
     * @return $this
     */
    public function merge_hidden($attributes): \Illuminate\Database\Eloquent\Model
    {
        return $this->each->merge_hidden($attributes);
    }
    /**
     * Set the hidden attributes across the entire collection.
     *
     * @param  array<int, string>  $hidden
     * @return $this
     */
    public function set_hidden($hidden): \Illuminate\Database\Eloquent\Model
    {
        return $this->each->set_hidden($hidden);
    }
    /**
     * Make the given, typically hidden, attributes visible across the entire collection.
     *
     * @param  array<array-key, string>|string  $attributes
     * @return $this
     */
    public function make_visible($attributes): \Illuminate\Database\Eloquent\Model
    {
        return $this->each->make_visible($attributes);
    }
    /**
     * Merge the given, typically hidden, attributes visible across the entire collection.
     *
     * @param  array<array-key, string>|string  $attributes
     * @return $this
     */
    public function merge_visible($attributes): \Illuminate\Database\Eloquent\Model
    {
        return $this->each->merge_visible($attributes);
    }
    /**
     * Set the visible attributes across the entire collection.
     *
     * @param  array<int, string>  $visible
     * @return $this
     */
    public function set_visible($visible): \Illuminate\Database\Eloquent\Model
    {
        return $this->each->set_visible($visible);
    }
    /**
     * Append an attribute across the entire collection.
     *
     * @param  array<array-key, string>|string  $attributes
     * @return $this
     */
    public function append($attributes): \Illuminate\Database\Eloquent\Model
    {
        return $this->each->append($attributes);
    }
    /**
     * Sets the appends on every element of the collection, overwriting the existing appends for each.
     *
     * @param  array<array-key, mixed>  $appends
     * @return $this
     */
    public function set_appends(array $appends): \Illuminate\Database\Eloquent\Model
    {
        return $this->each->set_appends($appends);
    }
    /**
     * Remove appended properties from every element in the collection.
     *
     * @return $this
     */
    public function without_appends(): \Illuminate\Database\Eloquent\Model
    {
        return $this->set_appends([]);
    }
    /**
     * Get a dictionary keyed by primary keys.
     *
     * @param  iterable<array-key, TModel>|null  $items
     * @return array<array-key, TModel>
     */
    public function get_dictionary($items = null): array
    {
        $items = is_null($items) ? $this->items : $items;
        $dictionary = [];
        foreach ($items as $value) {
            $key = $this->get_dictionary_key($value->get_key());
            if ($key !== null) {
                $dictionary[$key] = $value;
            }
        }
        return $dictionary;
    }
    /**
     * The following methods are intercepted to always return base collections.
     */
    /**
     * {@inheritDoc}
     *
     * @return \Illuminate\Support\Collection<array-key, int>
     */
    #[\Override]
    public function count_by($count_by = null): static
    {
        return $this->to_base()->count_by($count_by);
    }
    /**
     * {@inheritDoc}
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    #[\Override]
    public function collapse(): static
    {
        return $this->to_base()->collapse();
    }
    /**
     * {@inheritDoc}
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    #[\Override]
    public function flatten($depth = INF): static
    {
        return $this->to_base()->flatten($depth);
    }
    /**
     * {@inheritDoc}
     *
     * @return \Illuminate\Support\Collection<TModel, TKey>
     */
    #[\Override]
    public function flip(): static
    {
        return $this->to_base()->flip();
    }
    /**
     * {@inheritDoc}
     *
     * @return \Illuminate\Support\Collection<int, TKey>
     */
    #[\Override]
    public function keys(): static
    {
        return $this->to_base()->keys();
    }
    /**
     * {@inheritDoc}
     *
     * @template TPadValue
     *
     * @return \Illuminate\Support\Collection<int, TModel|TPadValue>
     */
    #[\Override]
    public function pad($size, $value): static
    {
        return $this->to_base()->pad($size, $value);
    }
    /**
     * {@inheritDoc}
     *
     * @return \Illuminate\Support\Collection<int<0, 1>, static<TKey, TModel>>
     */
    #[\Override]
    public function partition($key, $operator = null, $value = null): static
    {
        return parent::partition(...func_get_args())->to_base();
    }
    /**
     * {@inheritDoc}
     *
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    #[\Override]
    public function pluck($value, $key = null): static
    {
        return $this->to_base()->pluck($value, $key);
    }
    /**
     * {@inheritDoc}
     *
     * @template TZipValue
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, TModel|TZipValue>>
     */
    #[\Override]
    public function zip($items): static
    {
        return $this->to_base()->zip(...func_get_args());
    }
    /**
     * Get the comparison function to detect duplicates.
     *
     * @return callable(TModel, TModel): bool
     */
    protected function duplicate_comparator($strict)
    {
        return fn($a, $b) => $a->is($b);
    }
    /**
     * Enable relationship autoloading for all models in this collection.
     *
     * @return $this
     */
    public function with_relationship_autoloading(): static
    {
        $callback = fn(array $tuples) => $this->load_missing_relationship_chain($tuples);
        foreach ($this as $model) {
            if (!$model->has_relation_autoload_callback()) {
                $model->autoload_relations_using($callback, $this);
            }
        }
        return $this;
    }
    /**
     * Get the type of the entities being queued.
     *
     * @return string|null
     *
     * @throws \LogicException
     */
    public function get_queueable_class()
    {
        if ($this->is_empty()) {
            return;
        }
        $class = $this->get_queueable_model_class($this->first());
        $this->each(function ($model) use ($class): void {
            if ($this->get_queueable_model_class($model) !== $class) {
                throw new LogicException('Queueing collections with multiple model types is not supported.');
            }
        });
        return $class;
    }
    /**
     * Get the queueable class name for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return string
     */
    protected function get_queueable_model_class($model)
    {
        return method_exists($model, 'getQueueableClassName') ? $model->get_queueable_class_name() : $model::class;
    }
    /**
     * Get the identifiers for all of the entities.
     *
     * @return array<int, mixed>
     */
    public function get_queueable_ids()
    {
        if ($this->is_empty()) {
            return [];
        }
        return $this->first() instanceof Queueable_Entity ? $this->map->get_queueable_id()->all() : $this->model_keys();
    }
    /**
     * Get the relationships of the entities being queued.
     *
     * @return array<int, string>
     */
    public function get_queueable_relations()
    {
        if ($this->is_empty()) {
            return [];
        }
        $relations = $this->map->get_queueable_relations()->all();
        if (count($relations) === 0 || $relations === [[]]) {
            return [];
        }
        if (count($relations) === 1) {
            return reset($relations);
        }
        return array_intersect(...array_values($relations));
    }
    /**
     * Get the connection of the entities being queued.
     *
     * @return string|null
     *
     * @throws \LogicException
     */
    public function get_queueable_connection()
    {
        if ($this->is_empty()) {
            return;
        }
        $connection = $this->first()->get_connection_name();
        $this->each(function ($model) use ($connection): void {
            if ($model->get_connection_name() !== $connection) {
                throw new LogicException('Queueing collections with multiple model connections is not supported.');
            }
        });
        return $connection;
    }
    /**
     * Get the Eloquent query builder from the collection.
     *
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     *
     * @throws \LogicException
     */
    public function to_query()
    {
        $model = $this->first();
        if (!$model) {
            throw new LogicException('Unable to create query for empty collection.');
        }
        $class = $model::class;
        if ($this->reject(fn($model): bool => $model instanceof $class)->is_not_empty()) {
            throw new LogicException('Unable to create query for collection with mixed types.');
        }
        return $model->new_model_query()->where_key($this->model_keys());
    }
}