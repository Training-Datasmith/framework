<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

class Model_Pruning_Starting
{
    /**
     * Create a new event instance.
     *
     * @param  array<class-string>  $models  The class names of the models that will be pruned.
     */
    public function __construct(public $models)
    {
    }
}