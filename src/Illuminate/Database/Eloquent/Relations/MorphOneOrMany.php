<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TResult
 *
 * @extends \Illuminate\Database\Eloquent\Relations\HasOneOrMany<TRelatedModel, TDeclaringModel, TResult>
 */
abstract class Morph_One_Or_Many extends Has_One_Or_Many
{
    /**
     * The class name of the parent model.
     *
     * @var class-string<TRelatedModel>
     */
    protected $morph_class;
    /**
     * Create a new morph one or many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param string $morphType
     * @param  string  $id
     * @param  string  $localKey
     */
    public function __construct(
        Builder $query,
        Model $parent,
        /**
         * The foreign key type for the relationship.
         */
        protected $morph_type,
        $id,
        $local_key
    )
    {
        $this->morph_class = $parent->get_morph_class();
        parent::__construct($query, $parent, $id, $local_key);
    }
    /**
     * Set the base constraints on the relation query.
     */
    public function add_constraints(): void
    {
        if (static::$constraints) {
            $this->get_relation_query()->where($this->morph_type, $this->morph_class);
            parent::add_constraints();
        }
    }
    /** @inheritDoc */
    public function add_eager_constraints(array $models): void
    {
        parent::add_eager_constraints($models);
        $this->get_relation_query()->where($this->morph_type, $this->morph_class);
    }
    /**
     * Create a new instance of the related model. Allow mass-assignment.
     *
     * @return TRelatedModel
     */
    public function force_create(array $attributes = [])
    {
        $attributes[$this->get_foreign_key_name()] = $this->get_parent_key();
        $attributes[$this->get_morph_type()] = $this->morph_class;
        return $this->apply_inverse_relation_to_model($this->related->force_create($attributes));
    }
    /**
     * Set the foreign ID and type for creating a related model.
     *
     * @param  TRelatedModel  $model
     * @return void
     */
    protected function set_foreign_attributes_for_create(Model $model)
    {
        $model->{$this->get_foreign_key_name()} = $this->get_parent_key();
        $model->{$this->get_morph_type()} = $this->morph_class;
        foreach ($this->get_query()->pending_attributes as $key => $value) {
            $attributes ??= $model->get_attributes();
            if (!array_key_exists($key, $attributes)) {
                $model->set_attribute($key, $value);
            }
        }
        $this->apply_inverse_relation_to_model($model);
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
            $values[$key][$this->get_morph_type()] = $this->get_morph_class();
        }
        return parent::upsert($values, $unique_by, $update);
    }
    /** @inheritDoc */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        return parent::get_relation_existence_query($query, $parent_query, $columns)->where($query->qualify_column($this->get_morph_type()), $this->morph_class);
    }
    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function get_qualified_morph_type()
    {
        return $this->morph_type;
    }
    /**
     * Get the plain morph type name without the table.
     *
     * @return string
     */
    public function get_morph_type()
    {
        return last(explode('.', $this->morph_type));
    }
    /**
     * Get the class name of the parent model.
     *
     * @return class-string<TRelatedModel>
     */
    public function get_morph_class()
    {
        return $this->morph_class;
    }
    /**
     * Get the possible inverse relations for the parent model.
     *
     * @return array<non-empty-string>
     */
    protected function get_possible_inverse_relations(): array
    {
        return array_unique([Str::before_last($this->get_morph_type(), '_type'), ...parent::get_possible_inverse_relations()]);
    }
}