<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\Compares_Related_Models;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Dictionary;
use Illuminate\Database\Eloquent\Relations\Concerns\Supports_Default_Models;
use function Illuminate\Support\enum_value;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, TDeclaringModel, ?TRelatedModel>
 */
class Belongs_To extends Relation
{
    use Compares_Related_Models;
    use Interacts_With_Dictionary;
    use Supports_Default_Models;
    /**
     * Create a new belongs to relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $child
     * @param  string  $foreignKey
     * @param  string  $ownerKey
     * @param  string  $relationName
     */
    public function __construct(
        Builder $query,
        /**
         * The child model instance of the relation.
         */
        protected \Illuminate\Database\Eloquent\Model $child,
        /**
         * The foreign key of the parent model.
         */
        protected $foreign_key,
        /**
         * The associated key on the parent model.
         */
        protected $owner_key,
        /**
         * The name of the relationship.
         */
        protected $relation_name
    )
    {
        parent::__construct($query, $this->child);
    }
    /** @inheritDoc */
    public function get_results()
    {
        if (is_null($this->get_foreign_key_from($this->child))) {
            return $this->get_default_for($this->parent);
        }
        return $this->query->first() ?: $this->get_default_for($this->parent);
    }
    /**
     * Set the base constraints on the relation query.
     */
    public function add_constraints(): void
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $key = $this->get_qualified_owner_key_name();
            $this->query->where($key, '=', $this->get_foreign_key_from($this->child));
        }
    }
    /** @inheritDoc */
    public function add_eager_constraints(array $models): void
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->get_qualified_owner_key_name();
        $where_in = $this->where_in_method($this->related, $this->owner_key);
        $this->where_in_eager($where_in, $key, $this->get_eager_model_keys($models));
    }
    /**
     * Gather the keys from an array of related models.
     *
     * @param  array<int, TDeclaringModel>  $models
     */
    protected function get_eager_model_keys(array $models): array
    {
        $keys = [];
        // First we need to gather all of the keys from the parent models so we know what
        // to query for via the eager loading query. We will add them to an array then
        // execute a "where in" statement to gather up all of those related records.
        foreach ($models as $model) {
            if (!is_null($value = $this->get_foreign_key_from($model))) {
                $keys[] = $value;
            }
        }
        sort($keys);
        return array_values(array_unique($keys));
    }
    /** @inheritDoc */
    public function init_relation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->set_relation($relation, $this->get_default_for($model));
        }
        return $models;
    }
    /** @inheritDoc */
    public function match(array $models, Eloquent_Collection $results, $relation): array
    {
        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = [];
        foreach ($results as $result) {
            $attribute = $this->get_dictionary_key($this->get_related_key_from($result));
            if ($attribute !== null) {
                $dictionary[$attribute] = $result;
            }
        }
        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            $attribute = $this->get_dictionary_key($this->get_foreign_key_from($model));
            if ($attribute !== null && isset($dictionary[$attribute])) {
                $model->set_relation($relation, $dictionary[$attribute]);
            }
        }
        return $models;
    }
    /**
     * Associate the model instance to the given parent.
     *
     * @param  TRelatedModel|int|string|null  $model
     * @return TDeclaringModel
     */
    public function associate($model): \Illuminate\Database\Eloquent\Model
    {
        $owner_key = $model instanceof Model ? $model->get_attribute($this->owner_key) : $model;
        $this->child->set_attribute($this->foreign_key, $owner_key);
        if ($model instanceof Model) {
            $this->child->set_relation($this->relation_name, $model);
        } else {
            $this->child->unset_relation($this->relation_name);
        }
        return $this->child;
    }
    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return TDeclaringModel
     */
    public function dissociate()
    {
        $this->child->set_attribute($this->foreign_key, null);
        return $this->child->set_relation($this->relation_name, null);
    }
    /**
     * Alias of "dissociate" method.
     *
     * @return TDeclaringModel
     */
    public function disassociate()
    {
        return $this->dissociate();
    }
    /**
     * Touch all of the related models for the relationship.
     */
    public function touch(): void
    {
        if (!is_null($this->get_parent_key())) {
            parent::touch();
        }
    }
    /** @inheritDoc */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        if ($parent_query->get_query()->from == $query->get_query()->from) {
            return $this->get_relation_existence_query_for_self_relation($query, $parent_query, $columns);
        }
        return $query->select($columns)->where_column($this->get_qualified_foreign_key_name(), '=', $query->qualify_column($this->owner_key));
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
        $query->select($columns)->from($query->get_model()->get_table() . ' as ' . $hash = $this->get_relation_count_hash());
        $query->get_model()->set_table($hash);
        return $query->where_column($hash . '.' . $this->owner_key, '=', $this->get_qualified_foreign_key_name());
    }
    /**
     * Determine if the related model has an auto-incrementing ID.
     */
    protected function relation_has_incrementing_id(): bool
    {
        return $this->related->get_incrementing() && in_array($this->related->get_key_type(), ['int', 'integer']);
    }
    /**
     * Make a new related instance for the given model.
     *
     * @param  TDeclaringModel  $parent
     * @return TRelatedModel
     */
    protected function new_related_instance_for(Model $parent)
    {
        return $this->related->new_instance();
    }
    /**
     * Get the child of the relationship.
     *
     * @return TDeclaringModel
     */
    public function get_child(): \Illuminate\Database\Eloquent\Model
    {
        return $this->child;
    }
    /**
     * Get the foreign key of the relationship.
     *
     * @return string
     */
    public function get_foreign_key_name()
    {
        return $this->foreign_key;
    }
    /**
     * Get the fully-qualified foreign key of the relationship.
     *
     * @return string
     */
    public function get_qualified_foreign_key_name()
    {
        return $this->child->qualify_column($this->foreign_key);
    }
    /**
     * Get the key value of the child's foreign key.
     *
     * @return mixed
     */
    public function get_parent_key()
    {
        return $this->get_foreign_key_from($this->child);
    }
    /**
     * Get the associated key of the relationship.
     *
     * @return string
     */
    public function get_owner_key_name()
    {
        return $this->owner_key;
    }
    /**
     * Get the fully-qualified associated key of the relationship.
     *
     * @return string
     */
    public function get_qualified_owner_key_name()
    {
        return $this->related->qualify_column($this->owner_key);
    }
    /**
     * Get the value of the model's foreign key.
     *
     * @param  TRelatedModel  $model
     * @return int|string
     */
    protected function get_related_key_from(Model $model)
    {
        return $model->{$this->owner_key};
    }
    /**
     * Get the value of the model's foreign key.
     *
     * @param  TDeclaringModel  $model
     * @return mixed
     */
    protected function get_foreign_key_from(Model $model)
    {
        $foreign_key = $model->{$this->foreign_key};
        return enum_value($foreign_key);
    }
    /**
     * Get the name of the relationship.
     *
     * @return string
     */
    public function get_relation_name()
    {
        return $this->relation_name;
    }
}