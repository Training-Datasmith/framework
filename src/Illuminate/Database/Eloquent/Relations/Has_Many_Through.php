<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Concerns\Interacts_With_Dictionary;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel, \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>>
 */
class Has_Many_Through extends Has_One_Or_Many_Through
{
    use Interacts_With_Dictionary;
    /**
     * Convert the relationship to a "has one through" relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     */
    public function one()
    {
        return Has_One_Through::no_constraints(fn(): \Illuminate\Database\Eloquent\Relations\Has_One_Through => new Has_One_Through(tap($this->get_query(), fn(Builder $query): array => $query->get_query()->joins = []), $this->far_parent, $this->through_parent, $this->get_first_key_name(), $this->get_foreign_key_name(), $this->get_local_key_name(), $this->get_second_local_key_name()));
    }
    /** @inheritDoc */
    public function init_relation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->set_relation($relation, $this->related->new_collection());
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
                $model->set_relation($relation, $this->related->new_collection($dictionary[$key]));
            }
        }
        return $models;
    }
    /** @inheritDoc */
    public function get_results()
    {
        return !is_null($this->far_parent->{$this->local_key}) ? $this->get() : $this->related->new_collection();
    }
}