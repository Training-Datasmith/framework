<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use RuntimeException;
class Relation_Not_Found_Exception extends RuntimeException
{
    /**
     * The name of the affected Eloquent model.
     *
     * @var string
     */
    public $model;
    /**
     * The name of the relation.
     *
     * @var string
     */
    public $relation;
    /**
     * Create a new exception instance.
     *
     * @param  object  $model
     * @param  string  $relation
     * @param  string|null  $type
     */
    public static function make($model, $relation, $type = null): static
    {
        $class = $model::class;
        $instance = new static(is_null($type) ? "Call to undefined relationship [{$relation}] on model [{$class}]." : "Call to undefined relationship [{$relation}] on model [{$class}] of type [{$type}].");
        $instance->model = $class;
        $instance->relation = $relation;
        return $instance;
    }
}