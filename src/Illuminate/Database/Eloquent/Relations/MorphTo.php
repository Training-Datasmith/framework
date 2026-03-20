<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Dictionary;
use Illuminate\Support\Arr;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\BelongsTo<TRelatedModel, TDeclaringModel>
 */
class Morph_To extends Belongs_To
{
    use Interacts_With_Dictionary;
    /**
     * The associated key on the parent model.
     *
     * @var string|null
     */
    protected $owner_key;
    /**
     * The models whose relations are being eager loaded.
     *
     * @var \Illuminate\Database\Eloquent\Collection<int, TDeclaringModel>
     */
    protected $models;
    /**
     * All of the models keyed by ID.
     *
     * @var array
     */
    protected $dictionary = [];
    /**
     * A buffer of dynamic calls to query macros.
     *
     * @var array
     */
    protected $macro_buffer = [];
    /**
     * A map of relations to load for each individual morph type.
     *
     * @var array
     */
    protected $morphable_eager_loads = [];
    /**
     * A map of relationship counts to load for each individual morph type.
     *
     * @var array
     */
    protected $morphable_eager_load_counts = [];
    /**
     * A map of constraints to apply for each individual morph type.
     *
     * @var array
     */
    protected $morphable_constraints = [];
    /**
     * Create a new morph to relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $foreignKey
     * @param  string|null  $ownerKey
     * @param string $morphType
     * @param  string  $relation
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $foreign_key,
        $owner_key,
        /**
         * The type of the polymorphic relation.
         */
        protected $morph_type,
        $relation
    )
    {
        parent::__construct($query, $parent, $foreign_key, $owner_key, $relation);
    }
    /** @inheritDoc */
    #[\Override]
    public function add_eager_constraints(array $models): void
    {
        $this->build_dictionary($this->models = new Eloquent_Collection($models));
    }
    /**
     * Build a dictionary with the models.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $models
     * @return void
     */
    protected function build_dictionary(Eloquent_Collection $models)
    {
        $is_associative = Arr::is_assoc($models->all());
        foreach ($models as $key => $model) {
            if ($model->{$this->morph_type}) {
                $morph_type_key = $this->get_dictionary_key($model->{$this->morph_type});
                $foreign_key_key = $this->get_dictionary_key($model->{$this->foreign_key});
                if ($morph_type_key === null) {
                    continue;
                }
                if ($foreign_key_key === null) {
                    continue;
                }
                if ($is_associative) {
                    $this->dictionary[$morph_type_key][$foreign_key_key][$key] = $model;
                } else {
                    $this->dictionary[$morph_type_key][$foreign_key_key][] = $model;
                }
            }
        }
    }
    /**
     * Get the results of the relationship.
     *
     * Called via eager load method of Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TDeclaringModel>
     */
    public function get_eager()
    {
        foreach (array_keys($this->dictionary) as $type) {
            $this->match_to_morph_parents($type, $this->get_results_by_type($type));
        }
        return $this->models;
    }
    /**
     * Get all of the relation results for a type.
     *
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    protected function get_results_by_type($type)
    {
        $instance = $this->create_model_by_type($type);
        $owner_key = $this->owner_key ?? $instance->get_key_name();
        $query = $this->replay_macros($instance->new_query())->merge_constraints_from($this->get_query())->with(array_merge($this->get_query()->get_eager_loads(), (array) ($this->morphable_eager_loads[$instance::class] ?? [])))->with_count((array) ($this->morphable_eager_load_counts[$instance::class] ?? []));
        if ($callback = $this->morphable_constraints[$instance::class] ?? null) {
            $callback($query);
        }
        $where_in = $this->where_in_method($instance, $owner_key);
        return $query->{$where_in}($instance->qualify_column($owner_key), $this->gather_keys_by_type($type, $instance->get_key_type()))->get();
    }
    /**
     * Gather all of the foreign keys for a given type.
     *
     * @param  string  $type
     * @param  string  $keyType
     */
    protected function gather_keys_by_type($type, $key_type): array
    {
        return $key_type !== 'string' ? array_keys($this->dictionary[$type]) : array_map(fn($model_id): string => (string) $model_id, array_filter(array_keys($this->dictionary[$type])));
    }
    /**
     * Create a new model instance by type.
     *
     * @param  string  $type
     * @return TRelatedModel
     */
    public function create_model_by_type($type)
    {
        $class = Model::get_actual_class_name_for_morph($type);
        return tap(new $class(), function ($instance): void {
            if (!$instance->get_connection_name()) {
                $instance->set_connection($this->get_connection()->get_name());
            }
        });
    }
    /** @inheritDoc */
    #[\Override]
    public function match(array $models, Eloquent_Collection $results, $relation): array
    {
        return $models;
    }
    /**
     * Match the results for a given type to their parents.
     *
     * @param  string  $type
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @return void
     */
    protected function match_to_morph_parents($type, Eloquent_Collection $results)
    {
        foreach ($results as $result) {
            $owner_key = !is_null($this->owner_key) ? $this->get_dictionary_key($result->{$this->owner_key}) : $result->get_key();
            if ($owner_key !== null && isset($this->dictionary[$type][$owner_key])) {
                foreach ($this->dictionary[$type][$owner_key] as $model) {
                    $model->set_relation($this->relation_name, $result);
                }
            }
        }
    }
    /**
     * Associate the model instance to the given parent.
     *
     * @param  TRelatedModel|null  $model
     * @return TDeclaringModel
     */
    #[\Override]
    public function associate($model)
    {
        if ($model instanceof Model) {
            $foreign_key = $this->owner_key && $model->{$this->owner_key} ? $this->owner_key : $model->get_key_name();
        }
        $this->parent->set_attribute($this->foreign_key, $model instanceof Model ? $model->{$foreign_key} : null);
        $this->parent->set_attribute($this->morph_type, $model instanceof Model ? $model->get_morph_class() : null);
        return $this->parent->set_relation($this->relation_name, $model);
    }
    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return TDeclaringModel
     */
    #[\Override]
    public function dissociate()
    {
        $this->parent->set_attribute($this->foreign_key, null);
        $this->parent->set_attribute($this->morph_type, null);
        return $this->parent->set_relation($this->relation_name, null);
    }
    /** @inheritDoc */
    #[\Override]
    public function touch(): void
    {
        if (!is_null($this->get_parent_key())) {
            parent::touch();
        }
    }
    /** @inheritDoc */
    #[\Override]
    protected function new_related_instance_for(Model $parent)
    {
        return $parent->{$this->get_relation_name()}()->get_related()->new_instance();
    }
    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function get_morph_type()
    {
        return $this->morph_type;
    }
    /**
     * Get the dictionary used by the relationship.
     *
     * @return array
     */
    public function get_dictionary()
    {
        return $this->dictionary;
    }
    /**
     * Specify which relations to load for a given morph type.
     *
     * @return $this
     */
    public function morph_with(array $with): static
    {
        $this->morphable_eager_loads = array_merge($this->morphable_eager_loads, $with);
        return $this;
    }
    /**
     * Specify which relationship counts to load for a given morph type.
     *
     * @return $this
     */
    public function morph_with_count(array $with_count): static
    {
        $this->morphable_eager_load_counts = array_merge($this->morphable_eager_load_counts, $with_count);
        return $this;
    }
    /**
     * Specify constraints on the query for a given morph type.
     *
     * @return $this
     */
    public function constrain(array $callbacks): static
    {
        $this->morphable_constraints = array_merge($this->morphable_constraints, $callbacks);
        return $this;
    }
    /**
     * Indicate that soft deleted models should be included in the results.
     *
     * @return $this
     */
    public function with_trashed()
    {
        $callback = fn($query) => $query->has_macro('withTrashed') ? $query->with_trashed() : $query;
        $this->macro_buffer[] = ['method' => 'when', 'parameters' => [true, $callback]];
        return $this->when(true, $callback);
    }
    /**
     * Indicate that soft deleted models should not be included in the results.
     *
     * @return $this
     */
    public function without_trashed()
    {
        $callback = fn($query) => $query->has_macro('withoutTrashed') ? $query->without_trashed() : $query;
        $this->macro_buffer[] = ['method' => 'when', 'parameters' => [true, $callback]];
        return $this->when(true, $callback);
    }
    /**
     * Indicate that only soft deleted models should be included in the results.
     *
     * @return $this
     */
    public function only_trashed()
    {
        $callback = fn($query) => $query->has_macro('onlyTrashed') ? $query->only_trashed() : $query;
        $this->macro_buffer[] = ['method' => 'when', 'parameters' => [true, $callback]];
        return $this->when(true, $callback);
    }
    /**
     * Replay stored macro calls on the actual related instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    protected function replay_macros(Builder $query): Builder
    {
        foreach ($this->macro_buffer as $macro) {
            $query->{$macro['method']}(...$macro['parameters']);
        }
        return $query;
    }
    /** @inheritDoc */
    #[\Override]
    public function get_qualified_owner_key_name()
    {
        if (is_null($this->owner_key)) {
            return '';
        }
        return parent::get_qualified_owner_key_name();
    }
    /**
     * Handle dynamic method calls to the relationship.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        try {
            $result = parent::__call($method, $parameters);
            if (in_array($method, ['select', 'selectRaw', 'selectSub', 'addSelect', 'withoutGlobalScopes'])) {
                $this->macro_buffer[] = compact('method', 'parameters');
            }
            return $result;
        } catch (BadMethodCallException) {
            $this->macro_buffer[] = compact('method', 'parameters');
            return $this;
        }
    }
}