<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Configuration;

use Closure;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\Register_Providers;
use Illuminate\Foundation\Events\Diagnosing_Health;
use Illuminate\Foundation\Http\Middleware\Prevent_Requests_During_Maintenance;
use Illuminate\Foundation\Support\Providers\Event_Service_Provider as AppEventServiceProvider;
use Illuminate\Foundation\Support\Providers\Route_Service_Provider as AppRouteServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Laravel\Folio\Folio;
class Application_Builder
{
    /**
     * The service provider that are marked for registration.
     */
    protected array $pending_providers = [];
    /**
     * Any additional routing callbacks that should be invoked while registering routes.
     */
    protected array $additional_routing_callbacks = [];
    /**
     * The Folio / page middleware that have been defined by the user.
     */
    protected array $page_middleware = [];
    /**
     * Create a new application builder instance.
     */
    public function __construct(protected Application $app)
    {
    }
    /**
     * Register the standard kernel classes for the application.
     *
     * @return $this
     */
    public function with_kernels(): static
    {
        $this->app->singleton(\Illuminate\Contracts\Http\Kernel::class, \Illuminate\Foundation\Http\Kernel::class);
        $this->app->singleton(\Illuminate\Contracts\Console\Kernel::class, \Illuminate\Foundation\Console\Kernel::class);
        return $this;
    }
    /**
     * Register additional service providers.
     *
     * @return $this
     */
    public function with_providers(array $providers = [], bool $with_bootstrap_providers = true): static
    {
        Register_Providers::merge($providers, $with_bootstrap_providers ? $this->app->get_bootstrap_providers_path() : null);
        return $this;
    }
    /**
     * Register the core event service provider for the application.
     *
     * @param  iterable<int, string>|bool  $discover
     * @return $this
     */
    public function with_events(iterable|bool $discover = true): static
    {
        if (is_iterable($discover)) {
            App_Event_Service_Provider::set_event_discovery_paths($discover);
        }
        if ($discover === false) {
            App_Event_Service_Provider::disable_event_discovery();
        }
        if (!isset($this->pending_providers[App_Event_Service_Provider::class])) {
            $this->app->booting(function (): void {
                $this->app->register(App_Event_Service_Provider::class);
            });
        }
        $this->pending_providers[App_Event_Service_Provider::class] = true;
        return $this;
    }
    /**
     * Register the broadcasting services for the application.
     *
     * @return $this
     */
    public function with_broadcasting(string $channels, array $attributes = []): static
    {
        $this->app->booted(function () use ($channels, $attributes): void {
            Broadcast::routes(!empty($attributes) ? $attributes : null);
            if (file_exists($channels)) {
                require $channels;
            }
        });
        return $this;
    }
    /**
     * Register the routing services for the application.
     *
     * @return $this
     */
    public function with_routing(?Closure $using = null, array|string|null $web = null, array|string|null $api = null, ?string $commands = null, ?string $channels = null, ?string $pages = null, ?string $health = null, string $api_prefix = 'api', ?callable $then = null): static
    {
        if (is_null($using) && (is_string($web) || is_array($web) || is_string($api) || is_array($api) || is_string($pages) || is_string($health)) || is_callable($then)) {
            $using = $this->build_routing_callback($web, $api, $pages, $health, $api_prefix, $then);
            if (is_string($health)) {
                Prevent_Requests_During_Maintenance::except($health);
            }
        }
        App_Route_Service_Provider::load_routes_using($using);
        $this->app->booting(function (): void {
            $this->app->register(App_Route_Service_Provider::class, force: true);
        });
        if (is_string($commands) && realpath($commands) !== false) {
            $this->with_commands([$commands]);
        }
        if (is_string($channels) && realpath($channels) !== false) {
            $this->with_broadcasting($channels);
        }
        return $this;
    }
    /**
     * Create the routing callback for the application.
     *
     * @return \Closure
     */
    protected function build_routing_callback(array|string|null $web, array|string|null $api, ?string $pages, ?string $health, string $api_prefix, ?callable $then)
    {
        return function () use ($web, $api, $pages, $health, $api_prefix, $then): void {
            if (is_string($api) || is_array($api)) {
                if (is_array($api)) {
                    foreach ($api as $api_route) {
                        if (realpath($api_route) !== false) {
                            Route::middleware('api')->prefix($api_prefix)->group($api_route);
                        }
                    }
                } else {
                    Route::middleware('api')->prefix($api_prefix)->group($api);
                }
            }
            if (is_string($health)) {
                Route::get($health, function (): \Illuminate\Contracts\Routing\Response_Factory|\Illuminate\Http\Response {
                    $exception = null;
                    try {
                        Event::dispatch(new Diagnosing_Health());
                    } catch (\Throwable $e) {
                        if (app()->has_debug_mode_enabled()) {
                            throw $e;
                        }
                        report($e);
                        $exception = $e->get_message();
                    }
                    return response(View::file(__DIR__ . '/../resources/health-up.blade.php', ['exception' => $exception]), status: $exception ? 500 : 200);
                });
            }
            if (is_string($web) || is_array($web)) {
                if (is_array($web)) {
                    foreach ($web as $web_route) {
                        if (realpath($web_route) !== false) {
                            Route::middleware('web')->group($web_route);
                        }
                    }
                } else {
                    Route::middleware('web')->group($web);
                }
            }
            foreach ($this->additional_routing_callbacks as $callback) {
                $callback();
            }
            if (is_string($pages) && realpath($pages) !== false && class_exists(Folio::class)) {
                Folio::route($pages, middleware: $this->page_middleware);
            }
            if (is_callable($then)) {
                $then($this->app);
            }
        };
    }
    /**
     * Register the global middleware, middleware groups, and middleware aliases for the application.
     *
     * @return $this
     */
    public function with_middleware(?callable $callback = null): static
    {
        $this->app->after_resolving(Http_Kernel::class, function ($kernel) use ($callback): void {
            $middleware = (new Middleware())->redirect_guests_to(fn(): string => route('login'));
            if (!is_null($callback)) {
                $callback($middleware);
            }
            $this->page_middleware = $middleware->get_page_middleware();
            $kernel->set_global_middleware($middleware->get_global_middleware());
            $kernel->set_middleware_groups($middleware->get_middleware_groups());
            $kernel->set_middleware_aliases($middleware->get_middleware_aliases());
            if ($priorities = $middleware->get_middleware_priority()) {
                $kernel->set_middleware_priority($priorities);
            }
            if ($priority_appends = $middleware->get_middleware_priority_appends()) {
                foreach ($priority_appends as $new_middleware => $after) {
                    $kernel->add_to_middleware_priority_after($after, $new_middleware);
                }
            }
            if ($priority_prepends = $middleware->get_middleware_priority_prepends()) {
                foreach ($priority_prepends as $new_middleware => $before) {
                    $kernel->add_to_middleware_priority_before($before, $new_middleware);
                }
            }
        });
        $this->app->after_resolving(Console_Kernel::class, function () use ($callback): void {
            if (!is_null($callback)) {
                $callback(new Middleware());
            }
        });
        return $this;
    }
    /**
     * Register additional Artisan commands with the application.
     *
     * @return $this
     */
    public function with_commands(array $commands = []): static
    {
        if (empty($commands)) {
            $commands = [$this->app->path('Console/Commands')];
        }
        $this->app->after_resolving(Console_Kernel::class, function ($kernel) use ($commands): void {
            [$commands, $paths] = (new Collection($commands))->partition(fn($command): bool => class_exists($command));
            [$routes, $paths] = $paths->partition(fn($path): bool => is_file($path));
            $this->app->booted(static function () use ($kernel, $commands, $paths, $routes): void {
                $kernel->add_commands($commands->all());
                $kernel->add_command_paths($paths->all());
                $kernel->add_command_route_paths($routes->all());
            });
        });
        return $this;
    }
    /**
     * Register additional Artisan route paths.
     *
     * @return $this
     */
    protected function with_command_routing(array $paths): static
    {
        $this->app->after_resolving(Console_Kernel::class, function ($kernel) use ($paths): void {
            $this->app->booted(fn() => $kernel->add_command_route_paths($paths));
        });
        return $this;
    }
    /**
     * Register the scheduled tasks for the application.
     *
     * @param  callable(\Illuminate\Console\Scheduling\Schedule $schedule): void  $callback
     * @return $this
     */
    public function with_schedule(callable $callback): static
    {
        Artisan::starting(fn() => $callback($this->app->make(Schedule::class)));
        return $this;
    }
    /**
     * Register and configure the application's exception handler.
     *
     * @param  callable(\Illuminate\Foundation\Configuration\Exceptions)|null  $using
     * @return $this
     */
    public function with_exceptions(?callable $using = null): static
    {
        $this->app->singleton(\Illuminate\Contracts\Debug\Exception_Handler::class, \Illuminate\Foundation\Exceptions\Handler::class);
        if ($using !== null) {
            $this->app->after_resolving(\Illuminate\Foundation\Exceptions\Handler::class, fn($handler) => $using(new Exceptions($handler)));
        }
        return $this;
    }
    /**
     * Register an array of container bindings to be bound when the application is booting.
     *
     * @return $this
     */
    public function with_bindings(array $bindings): static
    {
        return $this->registered(function ($app) use ($bindings): void {
            foreach ($bindings as $abstract => $concrete) {
                $app->bind($abstract, $concrete);
            }
        });
    }
    /**
     * Register an array of singleton container bindings to be bound when the application is booting.
     *
     * @return $this
     */
    public function with_singletons(array $singletons): static
    {
        return $this->registered(function ($app) use ($singletons): void {
            foreach ($singletons as $abstract => $concrete) {
                if (is_string($abstract)) {
                    $app->singleton($abstract, $concrete);
                } else {
                    $app->singleton($concrete);
                }
            }
        });
    }
    /**
     * Register an array of scoped singleton container bindings to be bound when the application is booting.
     *
     * @return $this
     */
    public function with_scoped_singletons(array $scoped_singletons): static
    {
        return $this->registered(function ($app) use ($scoped_singletons): void {
            foreach ($scoped_singletons as $abstract => $concrete) {
                if (is_string($abstract)) {
                    $app->scoped($abstract, $concrete);
                } else {
                    $app->scoped($concrete);
                }
            }
        });
    }
    /**
     * Register a callback to be invoked when the application's service providers are registered.
     *
     * @return $this
     */
    public function registered(callable $callback): static
    {
        $this->app->registered($callback);
        return $this;
    }
    /**
     * Register a callback to be invoked when the application is "booting".
     *
     * @return $this
     */
    public function booting(callable $callback): static
    {
        $this->app->booting($callback);
        return $this;
    }
    /**
     * Register a callback to be invoked when the application is "booted".
     *
     * @return $this
     */
    public function booted(callable $callback): static
    {
        $this->app->booted($callback);
        return $this;
    }
    /**
     * Get the application instance.
     */
    public function create(): \Illuminate\Foundation\Application
    {
        return $this->app;
    }
}