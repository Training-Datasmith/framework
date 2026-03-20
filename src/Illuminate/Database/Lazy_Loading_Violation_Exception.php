<?php

declare (strict_types=1);
namespace Illuminate\Database;

use RuntimeException;
class Lazy_Loading_Violation_Exception extends RuntimeException
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
     */
    public function __construct($model, $relation)
    {
        $class = $model::class;
        parent::__construct("Attempted to lazy load [{$relation}] on model [{$class}] but lazy loading is disabled.");
        $this->model = $class;
        $this->relation = $relation;
    }
}