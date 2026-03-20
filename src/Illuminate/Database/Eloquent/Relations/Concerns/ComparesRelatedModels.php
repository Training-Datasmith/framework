<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations\Concerns;

use Illuminate\Contracts\Database\Eloquent\Supports_Partial_Relations;
use Illuminate\Database\Eloquent\Model;
trait Compares_Related_Models
{
    /**
     * Determine if the model is the related instance of the relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @return bool
     */
    public function is($model)
    {
        $match = !is_null($model) && $this->compare_keys($this->get_parent_key(), $this->get_related_key_from($model)) && $this->related->get_table() === $model->get_table() && $this->related->get_connection_name() === $model->get_connection_name();
        if ($match && $this instanceof Supports_Partial_Relations && $this->is_one_of_many()) {
            return $this->query->where_key($model->get_key())->exists();
        }
        return $match;
    }
    /**
     * Determine if the model is not the related instance of the relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     */
    public function is_not($model): bool
    {
        return !$this->is($model);
    }
    /**
     * Get the value of the parent model's key.
     *
     * @return mixed
     */
    abstract public function get_parent_key();
    /**
     * Get the value of the model's related key.
     *
     * @return mixed
     */
    abstract protected function get_related_key_from(Model $model);
    /**
     * Compare the parent key with the related key.
     *
     * @param  mixed  $parentKey
     * @param  mixed  $relatedKey
     * @return bool
     */
    protected function compare_keys($parent_key, $related_key)
    {
        if (empty($parent_key) || empty($related_key)) {
            return false;
        }
        if (is_int($parent_key) || is_int($related_key)) {
            return (int) $parent_key === (int) $related_key;
        }
        return $parent_key === $related_key;
    }
}