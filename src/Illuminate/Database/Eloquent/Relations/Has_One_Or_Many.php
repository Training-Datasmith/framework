<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Dictionary;
use Illuminate\Database\Eloquent\Relations\Concerns\Supports_Inverse_Relations;
use Illuminate\Database\Unique_Constraint_Violation_Exception;
use Illuminate\Support\Arr;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TResult
 *
 * @extends \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, TDeclaringModel, TResult>
 */
abstract class Has_One_Or_Many extends Relation
{
    use Interacts_With_Dictionary;
    use Supports_Inverse_Relations;
    /**
     * Create a new has one or many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     */
    public function __construct(
        Builder $query,
        Model $parent,
        /**
         * The foreign key of the parent model.
         */
        protected $foreign_key,
        /**
         * The local key of the parent model.
         */
        protected $local_key
    )
    {
        parent::__construct($query, $parent);
    }
    /**
     * Create and return an un-saved instance of the related model.
     *
     * @return TRelatedModel
     */
    public function make(array $attributes = [])
    {
        return tap($this->related->new_instance($attributes), function (\Illuminate\Database\Eloquent\Model $instance): void {
            $this->set_foreign_attributes_for_create($instance);
            $this->apply_inverse_relation_to_model($instance);
        });
    }
    /**
     * Create and return an un-saved instance of the related models.
     *
     * @param  iterable  $records
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    public function make_many($records)
    {
        $instances = $this->related->new_collection();
        foreach ($records as $record) {
            $instances->push($this->make($record));
        }
        return $instances;
    }
    /**
     * Set the base constraints on the relation query.
     */
    public function add_constraints(): void
    {
        if (static::$constraints) {
            $query = $this->get_relation_query();
            $query->where($this->foreign_key, '=', $this->get_parent_key());
            $query->where_not_null($this->foreign_key);
        }
    }
    /** @inheritDoc */
    public function add_eager_constraints(array $models): void
    {
        $where_in = $this->where_in_method($this->parent, $this->local_key);
        $this->where_in_eager($where_in, $this->foreign_key, $this->get_keys($models, $this->local_key), $this->get_relation_query());
    }
    /**
     * Match the eagerly loaded results to their single parents.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function match_one(array $models, Eloquent_Collection $results, $relation)
    {
        return $this->match_one_or_many($models, $results, $relation, 'one');
    }
    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function match_many(array $models, Eloquent_Collection $results, $relation)
    {
        return $this->match_one_or_many($models, $results, $relation, 'many');
    }
    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @param  string  $type
     * @return array<int, TDeclaringModel>
     */
    protected function match_one_or_many(array $models, Eloquent_Collection $results, $relation, $type)
    {
        $dictionary = $this->build_dictionary($results);
        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $this->get_dictionary_key($model->get_attribute($this->local_key));
            if ($key !== null && isset($dictionary[$key])) {
                $related = $this->get_relation_value($dictionary, $key, $type);
                $model->set_relation($relation, $related);
                // Apply the inverse relation if we have one...
                $type === 'one' ? $this->apply_inverse_relation_to_model($related, $model) : $this->apply_inverse_relation_to_collection($related, $model);
            }
        }
        return $models;
    }
    /**
     * Get the value of a relationship by one or many type.
     *
     * @param  string  $key
     * @param  string  $type
     * @return mixed
     */
    protected function get_relation_value(array $dictionary, $key, $type)
    {
        $value = $dictionary[$key];
        return $type === 'one' ? reset($value) : $this->related->new_collection($value);
    }
    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>  $results
     * @return array<array<array-key, TRelatedModel>>
     */
    protected function build_dictionary(Eloquent_Collection $results)
    {
        $foreign = $this->get_foreign_key_name();
        $dictionary = [];
        $is_associative = Arr::is_assoc($results->all());
        foreach ($results as $key => $item) {
            $pair_key = $this->get_dictionary_key($item->{$foreign});
            if ($pair_key === null) {
                continue;
            }
            if ($is_associative) {
                $dictionary[$pair_key][$key] = $item;
            } else {
                $dictionary[$pair_key][] = $item;
            }
        }
        return $dictionary;
    }
    /**
     * Find a model by its primary key or return a new instance of the related model.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? \Illuminate\Database\Eloquent\Collection<int, TRelatedModel> : TRelatedModel)
     */
    public function find_or_new($id, $columns = ['*'])
    {
        if (is_null($instance = $this->find($id, $columns))) {
            $instance = $this->related->new_instance();
            $this->set_foreign_attributes_for_create($instance);
        }
        return $instance;
    }
    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @return TRelatedModel
     */
    public function first_or_new(array $attributes = [], array $values = [])
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->related->new_instance(array_merge($attributes, $values));
            $this->set_foreign_attributes_for_create($instance);
        }
        return $instance;
    }
    /**
     * Get the first record matching the attributes. If the record is not found, create it.
     *
     * @param  (\Closure(): array)|array  $values
     * @return TRelatedModel
     */
    public function first_or_create(array $attributes = [], Closure|array $values = [])
    {
        if (is_null($instance = (clone $this)->where($attributes)->first())) {
            return $this->create_or_first($attributes, $values);
        }
        return $instance;
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
        } catch (Unique_Constraint_Violation_Exception $e) {
            return $this->use_write_pdo()->where($attributes)->first() ?? throw $e;
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
     * Insert new records or update the existing ones.
     *
     * @param  array|null  $update
     * @return int
     */
    public function upsert(array $values, array|string $unique_by, $update = null)
    {
        if (!empty($values) && !is_array(array_first($values))) {
            $values = [$values];
        }
        foreach ($values as $key => $value) {
            $values[$key][$this->get_foreign_key_name()] = $this->get_parent_key();
        }
        return $this->get_query()->upsert($values, $unique_by, $update);
    }
    /**
     * Attach a model instance to the parent model.
     *
     * @param  TRelatedModel  $model
     * @return TRelatedModel|false
     */
    public function save(Model $model)
    {
        $this->set_foreign_attributes_for_create($model);
        return $model->save() ? $model : false;
    }
    /**
     * Attach a model instance without raising any events to the parent model.
     *
     * @param  TRelatedModel  $model
     * @return TRelatedModel|false
     */
    public function save_quietly(Model $model)
    {
        return Model::without_events(fn() => $this->save($model));
    }
    /**
     * Attach a collection of models to the parent instance.
     *
     * @param  iterable<TRelatedModel>  $models
     * @return iterable<TRelatedModel>
     */
    public function save_many($models)
    {
        foreach ($models as $model) {
            $this->save($model);
        }
        return $models;
    }
    /**
     * Attach a collection of models to the parent instance without raising any events to the parent model.
     *
     * @param  iterable<TRelatedModel>  $models
     * @return iterable<TRelatedModel>
     */
    public function save_many_quietly($models)
    {
        return Model::without_events(fn() => $this->save_many($models));
    }
    /**
     * Create a new instance of the related model.
     *
     * @return TRelatedModel
     */
    public function create(array $attributes = [])
    {
        return tap($this->related->new_instance($attributes), function (\Illuminate\Database\Eloquent\Model $instance): void {
            $this->set_foreign_attributes_for_create($instance);
            $instance->save();
            $this->apply_inverse_relation_to_model($instance);
        });
    }
    /**
     * Create a new instance of the related model without raising any events to the parent model.
     *
     * @return TRelatedModel
     */
    public function create_quietly(array $attributes = [])
    {
        return Model::without_events(fn() => $this->create($attributes));
    }
    /**
     * Create a new instance of the related model. Allow mass-assignment.
     *
     * @return TRelatedModel
     */
    public function force_create(array $attributes = [])
    {
        $attributes[$this->get_foreign_key_name()] = $this->get_parent_key();
        return $this->apply_inverse_relation_to_model($this->related->force_create($attributes));
    }
    /**
     * Create a new instance of the related model with mass assignment without raising model events.
     *
     * @return TRelatedModel
     */
    public function force_create_quietly(array $attributes = [])
    {
        return Model::without_events(fn() => $this->force_create($attributes));
    }
    /**
     * Create a Collection of new instances of the related model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    public function create_many(iterable $records)
    {
        $instances = $this->related->new_collection();
        foreach ($records as $record) {
            $instances->push($this->create($record));
        }
        return $instances;
    }
    /**
     * Create a Collection of new instances of the related model without raising any events to the parent model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    public function create_many_quietly(iterable $records)
    {
        return Model::without_events(fn() => $this->create_many($records));
    }
    /**
     * Create a Collection of new instances of the related model, allowing mass-assignment.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    public function force_create_many(iterable $records)
    {
        $instances = $this->related->new_collection();
        foreach ($records as $record) {
            $instances->push($this->force_create($record));
        }
        return $instances;
    }
    /**
     * Create a Collection of new instances of the related model, allowing mass-assignment and without raising any events to the parent model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    public function force_create_many_quietly(iterable $records)
    {
        return Model::without_events(fn() => $this->force_create_many($records));
    }
    /**
     * Set the foreign ID for creating a related model.
     *
     * @param  TRelatedModel  $model
     * @return void
     */
    protected function set_foreign_attributes_for_create(Model $model)
    {
        $model->set_attribute($this->get_foreign_key_name(), $this->get_parent_key());
        foreach ($this->get_query()->pending_attributes as $key => $value) {
            $attributes ??= $model->get_attributes();
            if (!array_key_exists($key, $attributes)) {
                $model->set_attribute($key, $value);
            }
        }
        $this->apply_inverse_relation_to_model($model);
    }
    /** @inheritDoc */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        if ($query->get_query()->from == $parent_query->get_query()->from) {
            return $this->get_relation_existence_query_for_self_relation($query, $parent_query, $columns);
        }
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
    public function get_relation_existence_query_for_self_relation(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        $query->from($query->get_model()->get_table() . ' as ' . $hash = $this->get_relation_count_hash());
        $query->get_model()->set_table($hash);
        return $query->select($columns)->where_column($this->get_qualified_parent_key_name(), '=', $hash . '.' . $this->get_foreign_key_name());
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
        if ($this->parent->exists) {
            $this->query->limit($value);
        } else {
            $this->query->group_limit($value, $this->get_existence_compare_key());
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
        return $this->get_qualified_foreign_key_name();
    }
    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function get_parent_key()
    {
        return $this->parent->get_attribute($this->local_key);
    }
    /**
     * Get the fully-qualified parent key name.
     *
     * @return string
     */
    public function get_qualified_parent_key_name()
    {
        return $this->parent->qualify_column($this->local_key);
    }
    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function get_foreign_key_name()
    {
        $segments = explode('.', $this->get_qualified_foreign_key_name());
        return array_last($segments);
    }
    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function get_qualified_foreign_key_name()
    {
        return $this->foreign_key;
    }
    /**
     * Get the local key for the relationship.
     *
     * @return string
     */
    public function get_local_key_name()
    {
        return $this->local_key;
    }
}