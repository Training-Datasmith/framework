<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Routing;

use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Route;

class PrecognitionCallableDispatcher extends CallableDispatcher
{
    /**
     * Dispatch a request to a given callable.
     *
     * @param  callable  $callable
     */
    public function dispatch(Route $route, $callable): void
    {
        $this->resolveParameters($route, $callable);

        abort(204, headers: ['Precognition-Success' => 'true']);
    }
}
