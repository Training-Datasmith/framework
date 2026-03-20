<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http\Middleware;

use Illuminate\Container\Container;
use Illuminate\Foundation\Routing\Precognition_Callable_Dispatcher;
use Illuminate\Foundation\Routing\Precognition_Controller_Dispatcher;
use Illuminate\Routing\Contracts\Callable_Dispatcher as CallableDispatcherContract;
use Illuminate\Routing\Contracts\Controller_Dispatcher as ControllerDispatcherContract;
class Handle_Precognitive_Requests
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The container instance.
         */
        protected \Illuminate\Container\Container $container
    )
    {
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next)
    {
        if (!$request->is_attempting_precognition()) {
            return $this->append_vary_header($request, $next($request));
        }
        $bindings = $this->container->get_bindings();
        $callable_binding = $bindings[Callable_Dispatcher_Contract::class] ?? null;
        $controller_binding = $bindings[Controller_Dispatcher_Contract::class] ?? null;
        $this->prepare_for_precognition($request);
        return tap($next($request), function ($response) use ($request, $callable_binding, $controller_binding): void {
            $response->headers->set('Precognition', 'true');
            $this->append_vary_header($request, $response);
            $this->restore_dispatchers($callable_binding, $controller_binding);
        });
    }
    /**
     * Prepare to handle a precognitive request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function prepare_for_precognition($request)
    {
        $request->attributes->set('precognitive', true);
        $this->container->bind(Callable_Dispatcher_Contract::class, fn($app): \Illuminate\Foundation\Routing\Precognition_Callable_Dispatcher => new Precognition_Callable_Dispatcher($app));
        $this->container->bind(Controller_Dispatcher_Contract::class, fn($app): \Illuminate\Foundation\Routing\Precognition_Controller_Dispatcher => new Precognition_Controller_Dispatcher($app));
    }
    /**
     * Append the appropriate "Vary" header to the given response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return \Illuminate\Http\Response
     */
    protected function append_vary_header($request, $response)
    {
        return tap($response, fn() => $response->headers->set('Vary', implode(', ', array_filter([$response->headers->get('Vary'), 'Precognition']))));
    }
    /**
     * Restore the original route dispatcher bindings.
     *
     * @param  array|null  $callableBinding
     * @param  array|null  $controllerBinding
     * @return void
     */
    protected function restore_dispatchers(array $callable_binding, array $controller_binding)
    {
        if ($callable_binding) {
            $this->container->bind(Callable_Dispatcher_Contract::class, $callable_binding['concrete'], $callable_binding['shared']);
        }
        if ($controller_binding) {
            $this->container->bind(Controller_Dispatcher_Contract::class, $controller_binding['concrete'], $controller_binding['shared']);
        }
    }
}