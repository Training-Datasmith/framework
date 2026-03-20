<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use RuntimeException;
class Invalid_Cast_Exception extends RuntimeException
{
    /**
     * The name of the affected Eloquent model.
     *
     * @var string
     */
    public $model;
    /**
     * The name of the column.
     *
     * @var string
     */
    public $column;
    /**
     * The name of the cast type.
     *
     * @var string
     */
    public $cast_type;
    /**
     * Create a new exception instance.
     *
     * @param  object  $model
     * @param  string  $column
     * @param  string  $castType
     */
    public function __construct($model, $column, $cast_type)
    {
        $class = $model::class;
        parent::__construct("Call to undefined cast [{$cast_type}] on column [{$column}] in model [{$class}].");
        $this->model = $class;
        $this->column = $column;
        $this->cast_type = $cast_type;
    }
}