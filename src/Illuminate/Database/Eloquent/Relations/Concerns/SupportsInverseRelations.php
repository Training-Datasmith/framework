<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relation_Not_Found_Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
trait Supports_Inverse_Relations
{
    /**
     * The name of the inverse relationship.
     */
    protected ?string $inverse_relationship = null;
    /**
     * Instruct Eloquent to link the related models back to the parent after the relationship query has run.
     *
     * Alias of "chaperone".
     *
     * @return $this
     */
    public function inverse(?string $relation = null)
    {
        return $this->chaperone($relation);
    }
    /**
     * Instruct Eloquent to link the related models back to the parent after the relationship query has run.
     *
     * @return $this
     */
    public function chaperone(?string $relation = null)
    {
        $relation ??= $this->guess_inverse_relation();
        if (!$relation || !$this->get_model()->is_relation($relation)) {
            throw Relation_Not_Found_Exception::make($this->get_model(), $relation ?: 'null');
        }
        if ($this->inverse_relationship === null && $relation) {
            $this->query->after_query(fn($result) => $this->inverse_relationship ? $this->apply_inverse_relation_to_collection($result, $this->get_parent()) : $result);
        }
        $this->inverse_relationship = $relation;
        return $this;
    }
    /**
     * Guess the name of the inverse relationship.
     */
    protected function guess_inverse_relation(): ?string
    {
        return Arr::first($this->get_possible_inverse_relations(), fn($relation): bool => $relation && $this->get_model()->is_relation($relation));
    }
    /**
     * Get the possible inverse relations for the parent model.
     *
     * @return array<non-empty-string>
     */
    protected function get_possible_inverse_relations(): array
    {
        return array_filter(array_unique([Str::camel(Str::before_last($this->get_foreign_key_name(), $this->get_parent()->get_key_name())), Str::camel(Str::before_last($this->get_parent()->get_foreign_key(), $this->get_parent()->get_key_name())), Str::camel(class_basename($this->get_parent())), 'owner', $this->get_parent()::class === $this->get_model()::class ? 'parent' : null]));
    }
    /**
     * Set the inverse relation on all models in a collection.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function apply_inverse_relation_to_collection($models, ?Model $parent = null)
    {
        $parent ??= $this->get_parent();
        foreach ($models as $model) {
            $model instanceof Model && $this->apply_inverse_relation_to_model($model, $parent);
        }
        return $models;
    }
    /**
     * Set the inverse relation on a model.
     */
    protected function apply_inverse_relation_to_model(Model $model, ?Model $parent = null): Model
    {
        if ($inverse = $this->get_inverse_relationship()) {
            $parent ??= $this->get_parent();
            $model->set_relation($inverse, $parent);
        }
        return $model;
    }
    /**
     * Get the name of the inverse relationship.
     *
     * @return string|null
     */
    public function get_inverse_relationship()
    {
        return $this->inverse_relationship;
    }
    /**
     * Remove the chaperone / inverse relationship for this query.
     *
     * Alias of "withoutChaperone".
     *
     * @return $this
     */
    public function without_inverse()
    {
        return $this->without_chaperone();
    }
    /**
     * Remove the chaperone / inverse relationship for this query.
     *
     * @return $this
     */
    public function without_chaperone()
    {
        $this->inverse_relationship = null;
        return $this;
    }
}