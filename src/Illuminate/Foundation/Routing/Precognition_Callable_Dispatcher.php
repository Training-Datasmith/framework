<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Routing;

use Illuminate\Routing\Callable_Dispatcher;
use Illuminate\Routing\Route;
class Precognition_Callable_Dispatcher extends Callable_Dispatcher
{
    /**
     * Dispatch a request to a given callable.
     *
     * @param  callable  $callable
     */
    public function dispatch(Route $route, $callable): void
    {
        $this->resolve_parameters($route, $callable);
        abort(204, headers: ['Precognition-Success' => 'true']);
    }
}