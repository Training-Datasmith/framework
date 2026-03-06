<?php

declare(strict_types=1);

namespace Illuminate\Routing;

class ViewController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        /**
         * The response factory implementation.
         */
        protected \Illuminate\Contracts\Routing\ResponseFactory $response
    ) {
    }

    /**
     * Invoke the controller method.
     *
     * @param  mixed  ...$args
     * @return \Illuminate\Http\Response
     */
    public function __invoke(...$args)
    {
        $routeParameters = array_filter($args, fn ($key): bool => ! in_array($key, ['view', 'data', 'status', 'headers']), ARRAY_FILTER_USE_KEY);

        $args['data'] = array_merge($args['data'], $routeParameters);

        return $this->response->view(
            $args['view'],
            $args['data'],
            $args['status'],
            $args['headers']
        );
    }

    /**
     * Execute an action on the controller.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function callAction($method, $parameters)
    {
        return $this->{$method}(...$parameters);
    }
}
