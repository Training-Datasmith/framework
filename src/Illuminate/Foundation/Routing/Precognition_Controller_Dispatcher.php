<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Routing;

use Illuminate\Routing\Controller_Dispatcher;
use Illuminate\Routing\Route;
use RuntimeException;
class Precognition_Controller_Dispatcher extends Controller_Dispatcher
{
    /**
     * Dispatch a request to a given controller and method.
     *
     * @param  mixed  $controller
     * @param  string  $method
     */
    public function dispatch(Route $route, $controller, $method): void
    {
        $this->ensure_method_exists($controller, $method);
        $this->resolve_parameters($route, $controller, $method);
        abort(204, headers: ['Precognition-Success' => 'true']);
    }
    /**
     * Ensure that the given method exists on the controller.
     *
     * @param  object  $controller
     * @param  string  $method
     * @return $this
     */
    protected function ensure_method_exists($controller, $method): static
    {
        if (method_exists($controller, $method)) {
            return $this;
        }
        $class = $controller::class;
        throw new RuntimeException("Attempting to predict the outcome of the [{$class}::{$method}()] method but the method is not defined.");
    }
}