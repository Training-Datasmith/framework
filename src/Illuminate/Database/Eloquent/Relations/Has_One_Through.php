<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Contracts\Database\Eloquent\Supports_Partial_Relations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\Can_Be_One_Of_Many;
use Illuminate\Database\Eloquent\Relations\Concerns\Compares_Related_Models;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Dictionary;
use Illuminate\Database\Eloquent\Relations\Concerns\Supports_Default_Models;
use Illuminate\Database\Query\Join_Clause;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel, ?TRelatedModel>
 */
class Has_One_Through extends Has_One_Or_Many_Through implements Supports_Partial_Relations
{
    use Compares_Related_Models;
    use Can_Be_One_Of_Many;
    use Interacts_With_Dictionary;
    use Supports_Default_Models;
    /** @inheritDoc */
    public function get_results()
    {
        if (is_null($this->get_parent_key())) {
            return $this->get_default_for($this->far_parent);
        }
        return $this->first() ?: $this->get_default_for($this->far_parent);
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
        $dictionary = $this->build_dictionary($results);
        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $this->get_dictionary_key($model->get_attribute($this->local_key));
            if ($key !== null && isset($dictionary[$key])) {
                $value = $dictionary[$key];
                $model->set_relation($relation, reset($value));
            }
        }
        return $models;
    }
    /** @inheritDoc */
    public function get_relation_existence_query(Builder $query, Builder $parent_query, $columns = ['*'])
    {
        if ($this->is_one_of_many()) {
            $this->merge_one_of_many_joins_to($query);
        }
        return parent::get_relation_existence_query($query, $parent_query, $columns);
    }
    /** @inheritDoc */
    public function add_one_of_many_sub_query_constraints(Builder $query, $column = null, $aggregate = null): void
    {
        $query->add_select([$this->get_qualified_first_key_name()]);
        // We need to join subqueries that aren't the inner-most subquery which is joined in the CanBeOneOfMany::ofMany method...
        if ($this->get_one_of_many_sub_query() !== null) {
            $this->perform_join($query);
        }
    }
    /** @inheritDoc */
    public function get_one_of_many_sub_query_select_columns(): array
    {
        return [$this->get_qualified_first_key_name()];
    }
    /** @inheritDoc */
    public function add_one_of_many_join_sub_query_constraints(Join_Clause $join): void
    {
        $join->on($this->qualify_sub_select_column($this->first_key), '=', $this->get_qualified_first_key_name());
    }
    /**
     * Make a new related instance for the given model.
     *
     * @param  TDeclaringModel  $parent
     * @return TRelatedModel
     */
    public function new_related_instance_for(Model $parent)
    {
        return $this->related->new_instance();
    }
    /** @inheritDoc */
    protected function get_related_key_from(Model $model)
    {
        return $model->get_attribute($this->get_foreign_key_name());
    }
    /** @inheritDoc */
    public function get_parent_key()
    {
        return $this->far_parent->get_attribute($this->local_key);
    }
}