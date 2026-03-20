<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TPivotModel of \Illuminate\Database\Eloquent\Relations\Pivot = \Illuminate\Database\Eloquent\Relations\MorphPivot
 * @template TAccessor of string = 'pivot'
 *
 * @extends \Illuminate\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, TDeclaringModel, TPivotModel, TAccessor>
 */
class Morph_To_Many extends Belongs_To_Many
{
    /**
     * The type of the polymorphic relation.
     */
    protected string $morph_type;
    /**
     * The class name of the morph type constraint.
     *
     * @var class-string<TRelatedModel>
     */
    protected $morph_class;
    /**
     * Create a new morph to many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @param  bool  $inverse
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $name,
        $table,
        $foreign_pivot_key,
        $related_pivot_key,
        $parent_key,
        $related_key,
        $relation_name = null,
        /**
         * Indicates if we are connecting the inverse of the relation.
         *
         * This primarily affects the morphClass constraint.
         */
        protected $inverse = false
    )
    {
        $this->morph_type = $name . '_type';
        $this->morph_class = $this->inverse ? $query->get_model()->get_morph_class() : $parent->get_morph_class();
        parent::__construct($query, $parent, $table, $foreign_pivot_key, $related_pivot_key, $parent_key, $related_key, $relation_name);
    }
    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function add_where_constraints(): static
    {
        parent::add_where_constraints();
        $this->query->where($this->qualify_pivot_column($this->morph_type), $this->morph_class);
        return $this;
    }
    /** @inheritDoc */
    public function add_eager_constraints(array $models): void
    {
        parent::add_eager_constraints($models);
        $this->query->where($this->qualify_pivot_column($this->morph_type), $this->morph_class);
    }
    /**
     * Create a new pivot attachment record.
     *
     * @param  int  $id
     * @param  bool  $timed
     * @return array
     */
    protected function base_attach_record($id, $timed)
    {
        return Arr::add(parent::base_attach_record($id, $timed), $this->morph_type, $this->morph_class);
    }
    /** @inheritDoc */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*']): \Illuminate\Database\Eloquent\Builder
    {
        return parent::get_relation_existence_query($query, $parent_query, $columns)->where($this->qualify_pivot_column($this->morph_type), $this->morph_class);
    }
    /**
     * Get the pivot models that are currently attached, filtered by related model keys.
     *
     * @param  mixed  $ids
     * @return \Illuminate\Support\Collection<int, TPivotModel>
     */
    protected function get_currently_attached_pivots_for_ids($ids = null): \Illuminate\Support\Collection
    {
        return parent::get_currently_attached_pivots_for_ids($ids)->map(fn($record): mixed => $record instanceof Morph_Pivot ? $record->set_morph_type($this->morph_type)->set_morph_class($this->morph_class) : $record);
    }
    /**
     * Create a new query builder for the pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function new_pivot_query()
    {
        return parent::new_pivot_query()->where($this->morph_type, $this->morph_class);
    }
    /**
     * Create a new pivot model instance.
     *
     * @param  bool  $exists
     * @return TPivotModel
     */
    public function new_pivot(array $attributes = [], $exists = false)
    {
        $using = $this->using;
        $attributes = array_merge([$this->morph_type => $this->morph_class], $attributes);
        $pivot = $using ? $using::from_raw_attributes($this->parent, $attributes, $this->table, $exists) : Morph_Pivot::from_attributes($this->parent, $attributes, $this->table, $exists);
        $pivot->set_pivot_keys($this->foreign_pivot_key, $this->related_pivot_key)->set_related_model($this->related)->set_morph_type($this->morph_type)->set_morph_class($this->morph_class);
        return $pivot;
    }
    /**
     * Get the pivot columns for the relation.
     *
     * "pivot_" is prefixed at each column for easy removal later.
     *
     * @return array
     */
    protected function aliased_pivot_columns()
    {
        return (new Collection([$this->foreign_pivot_key, $this->related_pivot_key, $this->morph_type, ...$this->pivot_columns]))->map(fn($column) => $this->qualify_pivot_column($column) . ' as pivot_' . $column)->unique()->all();
    }
    /**
     * Get the foreign key "type" name.
     */
    public function get_morph_type(): string
    {
        return $this->morph_type;
    }
    /**
     * Get the fully-qualified morph type for the relation.
     *
     * @return string
     */
    public function get_qualified_morph_type_name()
    {
        return $this->qualify_pivot_column($this->morph_type);
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
     * Get the indicator for a reverse relationship.
     *
     * @return bool
     */
    public function get_inverse()
    {
        return $this->inverse;
    }
}