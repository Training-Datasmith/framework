<?php

namespace Illuminate\Routing;

use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;

class PendingResourceRegistration
{
    use CreatesRegularExpressionRouteConstraints, Macroable;

    /**
     * The resource's registration status.
     *
     * @var bool
     */
    protected $registered = false;

    /**
     * Create a new pending resource registration instance.
     *
     * @param  string  $name
     * @param  string  $controller
     */
    public function __construct(
        /**
         * The resource registrar.
         */
        protected \Illuminate\Routing\ResourceRegistrar $registrar,
        /**
         * The resource name.
         */
        protected $name,
        /**
         * The resource controller.
         */
        protected $controller,
        /**
         * The resource options.
         */
        protected array $options
    )
    {
    }

    /**
     * Set the methods the controller should apply to.
     *
     * @param  mixed  $methods
     */
    public function only($methods): static
    {
        $this->options['only'] = is_array($methods) ? $methods : func_get_args();

        return $this;
    }

    /**
     * Set the methods the controller should exclude.
     *
     * @param  mixed  $methods
     */
    public function except($methods): static
    {
        $this->options['except'] = is_array($methods) ? $methods : func_get_args();

        return $this;
    }

    /**
     * Set the route names for controller actions.
     *
     * @param  array|string  $names
     */
    public function names($names): static
    {
        $this->options['names'] = $names;

        return $this;
    }

    /**
     * Set the route name for a controller action.
     *
     * @param  string  $method
     * @param  string  $name
     */
    public function name($method, $name): static
    {
        $this->options['names'][$method] = $name;

        return $this;
    }

    /**
     * Override the route parameter names.
     *
     * @param  array|string  $parameters
     */
    public function parameters($parameters): static
    {
        $this->options['parameters'] = $parameters;

        return $this;
    }

    /**
     * Override a route parameter's name.
     *
     * @param  string  $previous
     * @param  string  $new
     */
    public function parameter($previous, $new): static
    {
        $this->options['parameters'][$previous] = $new;

        return $this;
    }

    /**
     * Add middleware to the resource routes.
     *
     * @param  mixed  $middleware
     */
    public function middleware($middleware): static
    {
        $middleware = Arr::wrap($middleware);

        foreach ($middleware as $key => $value) {
            $middleware[$key] = (string) $value;
        }

        $this->options['middleware'] = $middleware;

        if (isset($this->options['middleware_for'])) {
            foreach ($this->options['middleware_for'] as $method => $value) {
                $this->options['middleware_for'][$method] = Router::uniqueMiddleware(array_merge(
                    Arr::wrap($value),
                    $middleware
                ));
            }
        }

        return $this;
    }

    /**
     * Specify middleware that should be added to the specified resource routes.
     *
     * @param  array|string  $methods
     * @param  array|string  $middleware
     * @return $this
     */
    public function middlewareFor($methods, $middleware): static
    {
        $methods = Arr::wrap($methods);
        $middleware = Arr::wrap($middleware);

        if (isset($this->options['middleware'])) {
            $middleware = Router::uniqueMiddleware(array_merge(
                $this->options['middleware'],
                $middleware
            ));
        }

        foreach ($methods as $method) {
            $this->options['middleware_for'][$method] = $middleware;
        }

        return $this;
    }

    /**
     * Specify middleware that should be removed from the resource routes.
     *
     * @param  array|string  $middleware
     * @return $this
     */
    public function withoutMiddleware($middleware): static
    {
        $this->options['excluded_middleware'] = array_merge(
            (array) ($this->options['excluded_middleware'] ?? []), Arr::wrap($middleware)
        );

        return $this;
    }

    /**
     * Specify middleware that should be removed from the specified resource routes.
     *
     * @param  array|string  $methods
     * @param  array|string  $middleware
     * @return $this
     */
    public function withoutMiddlewareFor($methods, $middleware): static
    {
        $methods = Arr::wrap($methods);
        $middleware = Arr::wrap($middleware);

        foreach ($methods as $method) {
            $this->options['excluded_middleware_for'][$method] = $middleware;
        }

        return $this;
    }

    /**
     * Add "where" constraints to the resource routes.
     *
     * @param  mixed  $wheres
     */
    public function where($wheres): static
    {
        $this->options['wheres'] = $wheres;

        return $this;
    }

    /**
     * Indicate that the resource routes should have "shallow" nesting.
     *
     * @param  bool  $shallow
     */
    public function shallow($shallow = true): static
    {
        $this->options['shallow'] = $shallow;

        return $this;
    }

    /**
     * Define the callable that should be invoked on a missing model exception.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function missing($callback): static
    {
        $this->options['missing'] = $callback;

        return $this;
    }

    /**
     * Indicate that the resource routes should be scoped using the given binding fields.
     */
    public function scoped(array $fields = []): static
    {
        $this->options['bindingFields'] = $fields;

        return $this;
    }

    /**
     * Define which routes should allow "trashed" models to be retrieved when resolving implicit model bindings.
     */
    public function withTrashed(array $methods = []): static
    {
        $this->options['trashed'] = $methods;

        return $this;
    }

    /**
     * Register the resource route.
     *
     * @return \Illuminate\Routing\RouteCollection
     */
    public function register()
    {
        $this->registered = true;

        return $this->registrar->register(
            $this->name, $this->controller, $this->options
        );
    }

    /**
     * Handle the object's destruction.
     */
    public function __destruct()
    {
        if (! $this->registered) {
            $this->register();
        }
    }
}
