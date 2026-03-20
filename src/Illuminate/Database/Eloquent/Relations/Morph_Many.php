<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\MorphOneOrMany<TRelatedModel, TDeclaringModel, \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>>
 */
class Morph_Many extends Morph_One_Or_Many
{
    /**
     * Convert the relationship to a "morph one" relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<TRelatedModel, TDeclaringModel>
     */
    public function one()
    {
        return Morph_One::no_constraints(fn() => tap(new Morph_One($this->get_query(), $this->get_parent(), $this->morph_type, $this->foreign_key, $this->local_key), function ($morph_one): void {
            if ($inverse = $this->get_inverse_relationship()) {
                $morph_one->inverse($inverse);
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
    /** @inheritDoc */
    public function force_create(array $attributes = [])
    {
        $attributes[$this->get_morph_type()] = $this->morph_class;
        return parent::force_create($attributes);
    }
}