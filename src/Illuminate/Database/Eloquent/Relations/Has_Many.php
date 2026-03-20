<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\HasOneOrMany<TRelatedModel, TDeclaringModel, \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>>
 */
class Has_Many extends Has_One_Or_Many
{
    /**
     * Convert the relationship to a "has one" relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<TRelatedModel, TDeclaringModel>
     */
    public function one()
    {
        return Has_One::no_constraints(fn() => tap(new Has_One($this->get_query(), $this->parent, $this->foreign_key, $this->local_key), function ($has_one): void {
            if ($inverse = $this->get_inverse_relationship()) {
                $has_one->inverse($inverse);
            }
        }));
    }
    /** @inheritDoc */
    public function get_results()
    {
        return !is_null($this->get_parent_key()) ? $this->query->get() : $this->related->new_collection();
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
    public function match(array $models, Eloquent_Collection $results, $relation)
    {
        return $this->match_many($models, $results, $relation);
    }
}