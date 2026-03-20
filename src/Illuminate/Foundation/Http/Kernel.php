<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http;

use Carbon\Carbon_Interval;
use DateTimeInterface;
use Illuminate\Contracts\Debug\Exception_Handler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Events\Request_Handled;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Interacts_With_Time;
use InvalidArgumentException;
use Throwable;
class Kernel implements Kernel_Contract
{
    use Interacts_With_Time;
    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [\Illuminate\Foundation\Bootstrap\Load_Environment_Variables::class, \Illuminate\Foundation\Bootstrap\Load_Configuration::class, \Illuminate\Foundation\Bootstrap\Handle_Exceptions::class, \Illuminate\Foundation\Bootstrap\Register_Facades::class, \Illuminate\Foundation\Bootstrap\Register_Providers::class, \Illuminate\Foundation\Bootstrap\Boot_Providers::class];
    /**
     * The application's middleware stack.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [];
    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middleware_groups = [];
    /**
     * The application's route middleware.
     *
     * @var array<string, class-string|string>
     *
     * @deprecated
     */
    protected $route_middleware = [];
    /**
     * The application's middleware aliases.
     *
     * @var array<string, class-string|string>
     */
    protected $middleware_aliases = [];
    /**
     * All of the registered request duration handlers.
     *
     * @var array
     */
    protected $request_lifecycle_duration_handlers = [];
    /**
     * When the kernel starting handling the current request.
     *
     * @var \Illuminate\Support\Carbon|null
     */
    protected $request_started_at;
    /**
     * The priority-sorted list of middleware.
     *
     * Forces non-global middleware to always be in the given order.
     *
     * @var string[]
     */
    protected $middleware_priority = [\Illuminate\Foundation\Http\Middleware\Handle_Precognitive_Requests::class, \Illuminate\Cookie\Middleware\Encrypt_Cookies::class, \Illuminate\Cookie\Middleware\Add_Queued_Cookies_To_Response::class, \Illuminate\Session\Middleware\Start_Session::class, \Illuminate\View\Middleware\Share_Errors_From_Session::class, \Illuminate\Contracts\Auth\Middleware\Authenticates_Requests::class, \Illuminate\Routing\Middleware\Throttle_Requests::class, \Illuminate\Routing\Middleware\Throttle_Requests_With_Redis::class, \Illuminate\Contracts\Session\Middleware\Authenticates_Sessions::class, \Illuminate\Routing\Middleware\Substitute_Bindings::class, \Illuminate\Auth\Middleware\Authorize::class];
    /**
     * Create a new HTTP kernel instance.
     */
    public function __construct(
        /**
         * The application implementation.
         */
        protected \Illuminate\Contracts\Foundation\Application $app,
        /**
         * The router instance.
         */
        protected \Illuminate\Routing\Router $router
    )
    {
        $this->sync_middleware_to_router();
    }
    /**
     * Handle an incoming HTTP request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handle($request)
    {
        $this->request_started_at = Carbon::now();
        try {
            $request->enable_http_method_parameter_override();
            $response = $this->send_request_through_router($request);
        } catch (Throwable $e) {
            $this->report_exception($e);
            $response = $this->render_exception($request, $e);
        }
        $this->app['events']->dispatch(new Request_Handled($request, $response));
        return $response;
    }
    /**
     * Send the given request through the middleware / router.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function send_request_through_router($request)
    {
        $this->app->instance('request', $request);
        Request::clear_resolved_instance();
        $this->bootstrap();
        return (new Pipeline($this->app))->send($request)->through($this->app->should_skip_middleware() ? [] : $this->middleware)->then($this->dispatch_to_router());
    }
    /**
     * Bootstrap the application for HTTP requests.
     */
    public function bootstrap(): void
    {
        if (!$this->app->has_been_bootstrapped()) {
            $this->app->bootstrap_with($this->bootstrappers());
        }
    }
    /**
     * Get the route dispatcher callback.
     *
     * @return \Closure
     */
    protected function dispatch_to_router()
    {
        return function ($request) {
            $this->app->instance('request', $request);
            return $this->router->dispatch($request);
        };
    }
    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     */
    public function terminate($request, $response): void
    {
        $this->app['events']->dispatch(new Terminating());
        $this->terminate_middleware($request, $response);
        $this->app->terminate();
        if ($this->request_started_at === null) {
            return;
        }
        $this->request_started_at->set_timezone($this->app['config']->get('app.timezone') ?? 'UTC');
        foreach ($this->request_lifecycle_duration_handlers as ['threshold' => $threshold, 'handler' => $handler]) {
            $end ??= Carbon::now();
            if ($this->request_started_at->diff_in_milliseconds($end) > $threshold) {
                $handler($this->request_started_at, $request, $response);
            }
        }
        $this->request_started_at = null;
    }
    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    protected function terminate_middleware($request, $response)
    {
        $middlewares = $this->app->should_skip_middleware() ? [] : array_merge($this->gather_route_middleware($request), $this->middleware);
        foreach ($middlewares as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }
            [$name] = $this->parse_middleware($middleware);
            $instance = $this->app->make($name);
            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }
    }
    /**
     * Register a callback to be invoked when the requests lifecycle duration exceeds a given amount of time.
     *
     * @param  \DateTimeInterface|\Carbon\CarbonInterval|float|int  $threshold
     * @param  callable  $handler
     */
    public function when_request_lifecycle_is_longer_than($threshold, $handler): void
    {
        $threshold = $threshold instanceof DateTimeInterface ? $this->seconds_until($threshold) * 1000 : $threshold;
        $threshold = $threshold instanceof Carbon_Interval ? $threshold->total_milliseconds : $threshold;
        $this->request_lifecycle_duration_handlers[] = ['threshold' => $threshold, 'handler' => $handler];
    }
    /**
     * When the request being handled started.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function request_started_at()
    {
        return $this->request_started_at;
    }
    /**
     * Gather the route middleware for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function gather_route_middleware($request)
    {
        if ($route = $request->route()) {
            return $this->router->gather_route_middleware($route);
        }
        return [];
    }
    /**
     * Parse a middleware string to get the name and parameters.
     *
     * @param  string  $middleware
     */
    protected function parse_middleware($middleware): array
    {
        [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, []);
        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }
        return [$name, $parameters];
    }
    /**
     * Determine if the kernel has a given middleware.
     *
     * @param  string  $middleware
     */
    public function has_middleware($middleware): bool
    {
        return in_array($middleware, $this->middleware);
    }
    /**
     * Add a new middleware to the beginning of the stack if it does not already exist.
     *
     * @param  string  $middleware
     * @return $this
     */
    public function prepend_middleware($middleware): static
    {
        if (array_search($middleware, $this->middleware) === false) {
            array_unshift($this->middleware, $middleware);
        }
        return $this;
    }
    /**
     * Add a new middleware to end of the stack if it does not already exist.
     *
     * @param  string  $middleware
     * @return $this
     */
    public function push_middleware($middleware): static
    {
        if (array_search($middleware, $this->middleware) === false) {
            $this->middleware[] = $middleware;
        }
        return $this;
    }
    /**
     * Prepend the given middleware to the given middleware group.
     *
     * @param  string  $group
     * @param  string  $middleware
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function prepend_middleware_to_group($group, $middleware): static
    {
        if (!isset($this->middleware_groups[$group])) {
            throw new InvalidArgumentException("The [{$group}] middleware group has not been defined.");
        }
        if (array_search($middleware, $this->middleware_groups[$group]) === false) {
            array_unshift($this->middleware_groups[$group], $middleware);
        }
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Append the given middleware to the given middleware group.
     *
     * @param  string  $group
     * @param  string  $middleware
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function append_middleware_to_group($group, $middleware): static
    {
        if (!isset($this->middleware_groups[$group])) {
            throw new InvalidArgumentException("The [{$group}] middleware group has not been defined.");
        }
        if (array_search($middleware, $this->middleware_groups[$group]) === false) {
            $this->middleware_groups[$group][] = $middleware;
        }
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Prepend the given middleware to the middleware priority list.
     *
     * @param  string  $middleware
     * @return $this
     */
    public function prepend_to_middleware_priority($middleware): static
    {
        if (!in_array($middleware, $this->middleware_priority)) {
            array_unshift($this->middleware_priority, $middleware);
        }
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Append the given middleware to the middleware priority list.
     *
     * @param  string  $middleware
     * @return $this
     */
    public function append_to_middleware_priority($middleware): static
    {
        if (!in_array($middleware, $this->middleware_priority)) {
            $this->middleware_priority[] = $middleware;
        }
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Add the given middleware to the middleware priority list before other middleware.
     *
     * @param  array|string  $before
     * @param  string  $middleware
     * @return $this
     */
    public function add_to_middleware_priority_before($before, $middleware): static
    {
        return $this->add_to_middleware_priority_relative($before, $middleware, after: false);
    }
    /**
     * Add the given middleware to the middleware priority list after other middleware.
     *
     * @param  array|string  $after
     * @param  string  $middleware
     * @return $this
     */
    public function add_to_middleware_priority_after($after, $middleware): static
    {
        return $this->add_to_middleware_priority_relative($after, $middleware);
    }
    /**
     * Add the given middleware to the middleware priority list relative to other middleware.
     *
     * @param  string|array  $existing
     * @param  string  $middleware
     * @param  bool  $after
     * @return $this
     */
    protected function add_to_middleware_priority_relative($existing, $middleware, $after = true): static
    {
        if (!in_array($middleware, $this->middleware_priority)) {
            $index = $after ? 0 : count($this->middleware_priority);
            foreach ((array) $existing as $existing_middleware) {
                if (in_array($existing_middleware, $this->middleware_priority)) {
                    $middleware_index = array_search($existing_middleware, $this->middleware_priority);
                    if ($after && $middleware_index > $index) {
                        $index = $middleware_index + 1;
                    } elseif ($after === false && $middleware_index < $index) {
                        $index = $middleware_index;
                    }
                }
            }
            if ($index === 0 && $after === false) {
                array_unshift($this->middleware_priority, $middleware);
            } elseif ($after && $index === 0 || $index === count($this->middleware_priority)) {
                $this->middleware_priority[] = $middleware;
            } else {
                array_splice($this->middleware_priority, $index, 0, $middleware);
            }
        }
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Sync the current state of the middleware to the router.
     *
     * @return void
     */
    protected function sync_middleware_to_router()
    {
        $this->router->middleware_priority = $this->middleware_priority;
        foreach ($this->middleware_groups as $key => $middleware) {
            $this->router->middleware_group($key, $middleware);
        }
        foreach (array_merge($this->route_middleware, $this->middleware_aliases) as $key => $middleware) {
            $this->router->alias_middleware($key, $middleware);
        }
    }
    /**
     * Get the priority-sorted list of middleware.
     *
     * @return array
     */
    public function get_middleware_priority()
    {
        return $this->middleware_priority;
    }
    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }
    /**
     * Report the exception to the exception handler.
     *
     * @return void
     */
    protected function report_exception(Throwable $e)
    {
        $this->app[Exception_Handler::class]->report($e);
    }
    /**
     * Render the exception to a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function render_exception($request, Throwable $e)
    {
        return $this->app[Exception_Handler::class]->render($request, $e);
    }
    /**
     * Get the application's global middleware.
     *
     * @return array
     */
    public function get_global_middleware()
    {
        return $this->middleware;
    }
    /**
     * Set the application's global middleware.
     *
     * @return $this
     */
    public function set_global_middleware(array $middleware): static
    {
        $this->middleware = $middleware;
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Get the application's route middleware groups.
     *
     * @return array
     */
    public function get_middleware_groups()
    {
        return $this->middleware_groups;
    }
    /**
     * Set the application's middleware groups.
     *
     * @return $this
     */
    public function set_middleware_groups(array $groups): static
    {
        $this->middleware_groups = $groups;
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Get the application's route middleware aliases.
     *
     *
     * @deprecated
     */
    public function get_route_middleware(): array
    {
        return $this->get_middleware_aliases();
    }
    /**
     * Get the application's route middleware aliases.
     */
    public function get_middleware_aliases(): array
    {
        return array_merge($this->route_middleware, $this->middleware_aliases);
    }
    /**
     * Set the application's route middleware aliases.
     *
     * @return $this
     */
    public function set_middleware_aliases(array $aliases): static
    {
        $this->middleware_aliases = $aliases;
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Set the application's middleware priority.
     *
     * @return $this
     */
    public function set_middleware_priority(array $priority): static
    {
        $this->middleware_priority = $priority;
        $this->sync_middleware_to_router();
        return $this;
    }
    /**
     * Get the Laravel application instance.
     */
    public function get_application(): \Illuminate\Contracts\Foundation\Application
    {
        return $this->app;
    }
    /**
     * Set the Laravel application instance.
     *
     * @return $this
     */
    public function set_application(Application $app): static
    {
        $this->app = $app;
        return $this;
    }
}