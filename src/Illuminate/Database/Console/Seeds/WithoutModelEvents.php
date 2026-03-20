<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Seeds;

use Illuminate\Database\Eloquent\Model;
trait Without_Model_Events
{
    /**
     * Prevent model events from being dispatched by the given callback.
     *
     * @return callable
     */
    public function without_model_events(callable $callback)
    {
        return fn() => Model::without_events($callback);
    }
}