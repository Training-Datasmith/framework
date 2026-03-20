<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Support\Providers;

use Closure;
use Illuminate\Contracts\Routing\Url_Generator;
use Illuminate\Routing\Router;
use Illuminate\Support\Service_Provider;
use Illuminate\Support\Traits\Forwards_Calls;
/**
 * @mixin \Illuminate\Routing\Router
 */
class Route_Service_Provider extends Service_Provider
{
    use Forwards_Calls;
    /**
     * The controller namespace for the application.
     *
     * @var string|null
     */
    protected $namespace;
    /**
     * The callback that should be used to load the application's routes.
     *
     * @var \Closure|null
     */
    protected $load_routes_using;
    /**
     * The global callback that should be used to load the application's routes.
     *
     * @var \Closure|null
     */
    protected static $always_load_routes_using;
    /**
     * The callback that should be used to load the application's cached routes.
     *
     * @var \Closure|null
     */
    protected static $always_load_cached_routes_using;
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->booted(function (): void {
            $this->set_root_controller_namespace();
            if ($this->routes_are_cached()) {
                $this->load_cached_routes();
            } else {
                $this->load_routes();
                $this->app->booted(function (): void {
                    $this->app['router']->get_routes()->refresh_name_lookups();
                    $this->app['router']->get_routes()->refresh_action_lookups();
                });
            }
        });
    }
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
    /**
     * Register the callback that will be used to load the application's routes.
     *
     * @return $this
     */
    protected function routes(Closure $routes_callback): static
    {
        $this->load_routes_using = $routes_callback;
        return $this;
    }
    /**
     * Register the callback that will be used to load the application's routes.
     */
    public static function load_routes_using(?Closure $routes_callback): void
    {
        self::$always_load_routes_using = $routes_callback;
    }
    /**
     * Register the callback that will be used to load the application's cached routes.
     */
    public static function load_cached_routes_using(?Closure $routes_callback): void
    {
        self::$always_load_cached_routes_using = $routes_callback;
    }
    /**
     * Set the root controller namespace for the application.
     *
     * @return void
     */
    protected function set_root_controller_namespace()
    {
        if (!is_null($this->namespace)) {
            $this->app[Url_Generator::class]->set_root_controller_namespace($this->namespace);
        }
    }
    /**
     * Determine if the application routes are cached.
     *
     * @return bool
     */
    protected function routes_are_cached()
    {
        return $this->app->routes_are_cached();
    }
    /**
     * Load the cached routes for the application.
     *
     * @return void
     */
    protected function load_cached_routes()
    {
        if (!is_null(self::$always_load_cached_routes_using)) {
            $this->app->call(self::$always_load_cached_routes_using);
            return;
        }
        $this->app->booted(function (): void {
            require $this->app->get_cached_routes_path();
        });
    }
    /**
     * Load the application routes.
     *
     * @return void
     */
    protected function load_routes()
    {
        if (!is_null(self::$always_load_routes_using)) {
            $this->app->call(self::$always_load_routes_using);
        }
        if (!is_null($this->load_routes_using)) {
            $this->app->call($this->load_routes_using);
        } elseif (method_exists($this, 'map')) {
            $this->app->call([$this, 'map']);
        }
    }
    /**
     * Pass dynamic methods onto the router instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forward_call_to($this->app->make(Router::class), $method, $parameters);
    }
}