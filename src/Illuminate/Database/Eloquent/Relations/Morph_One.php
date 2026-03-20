<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Contracts\Database\Eloquent\Supports_Partial_Relations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\Can_Be_One_Of_Many;
use Illuminate\Database\Eloquent\Relations\Concerns\Compares_Related_Models;
use Illuminate\Database\Eloquent\Relations\Concerns\Supports_Default_Models;
use Illuminate\Database\Query\Join_Clause;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\MorphOneOrMany<TRelatedModel, TDeclaringModel, ?TRelatedModel>
 */
class Morph_One extends Morph_One_Or_Many implements Supports_Partial_Relations
{
    use Can_Be_One_Of_Many;
    use Compares_Related_Models;
    use Supports_Default_Models;
    /** @inheritDoc */
    public function get_results()
    {
        if (is_null($this->get_parent_key())) {
            return $this->get_default_for($this->parent);
        }
        return $this->query->first() ?: $this->get_default_for($this->parent);
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
    public function match(array $models, Eloquent_Collection $results, $relation)
    {
        return $this->match_one($models, $results, $relation);
    }
    /** @inheritDoc */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        if ($this->is_one_of_many()) {
            $this->merge_one_of_many_joins_to($query);
        }
        return parent::get_relation_existence_query($query, $parent_query, $columns);
    }
    /**
     * Add constraints for inner join subselect for one of many relationships.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  string|null  $column
     * @param  string|null  $aggregate
     */
    public function add_one_of_many_sub_query_constraints(Builder $query, $column = null, $aggregate = null): void
    {
        $query->add_select($this->foreign_key, $this->morph_type);
    }
    /**
     * Get the columns that should be selected by the one of many subquery.
     *
     * @return array|string
     */
    public function get_one_of_many_sub_query_select_columns(): array
    {
        return [$this->foreign_key, $this->morph_type];
    }
    /**
     * Add join query constraints for one of many relationships.
     */
    public function add_one_of_many_join_sub_query_constraints(Join_Clause $join): void
    {
        $join->on($this->qualify_sub_select_column($this->morph_type), '=', $this->qualify_related_column($this->morph_type))->on($this->qualify_sub_select_column($this->foreign_key), '=', $this->qualify_related_column($this->foreign_key));
    }
    /**
     * Make a new related instance for the given model.
     *
     * @param  TDeclaringModel  $parent
     * @return TRelatedModel
     */
    public function new_related_instance_for(Model $parent)
    {
        return tap($this->related->new_instance(), function (\Illuminate\Database\Eloquent\Model $instance) use ($parent): void {
            $instance->set_attribute($this->get_foreign_key_name(), $parent->{$this->local_key})->set_attribute($this->get_morph_type(), $this->morph_class);
            $this->apply_inverse_relation_to_model($instance, $parent);
        });
    }
    /**
     * Get the value of the model's foreign key.
     *
     * @param  TRelatedModel  $model
     * @return int|string
     */
    protected function get_related_key_from(Model $model)
    {
        return $model->get_attribute($this->get_foreign_key_name());
    }
}