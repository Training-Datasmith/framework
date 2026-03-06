<?php

namespace Illuminate\Routing;

class RouteFileRegistrar
{
    /**
     * Create a new route file registrar instance.
     */
    public function __construct(
        /**
         * The router instance.
         */
        protected \Illuminate\Routing\Router $router
    )
    {
    }

    /**
     * Require the given routes file.
     *
     * @param  string  $routes
     */
    public function register($routes): void
    {
        $router = $this->router;

        require $routes;
    }
}
