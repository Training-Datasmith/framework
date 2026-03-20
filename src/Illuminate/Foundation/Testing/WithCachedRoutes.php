<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Support\Providers\Route_Service_Provider;
trait With_Cached_Routes
{
    /**
     * After creating the routes once, we can cache them for the remaining tests.
     */
    protected function set_up_with_cached_routes(): void
    {
        if ((Cached_State::$cached_routes ?? null) === null) {
            $routes = $this->app['router']->get_routes();
            $routes->refresh_name_lookups();
            $routes->refresh_action_lookups();
            Cached_State::$cached_routes = $routes->compile();
        }
        $this->mark_routes_cached($this->app);
    }
    /**
     * Reset the route service provider so it's not defaulting to loading cached routes.
     *
     * This is helpful if some of the tests in the suite apply this trait while others do not.
     */
    protected function tear_down_with_cached_routes(): void
    {
        Route_Service_Provider::load_cached_routes_using(null);
    }
    /**
     * Inform the container to treat routes as cached.
     */
    protected function mark_routes_cached(Application $app): void
    {
        $app->instance('routes.cached', true);
        Route_Service_Provider::load_cached_routes_using(static fn() => app('router')->set_compiled_routes(Cached_State::$cached_routes));
    }
}