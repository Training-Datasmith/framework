<?php

declare (strict_types=1);
namespace Illuminate\Http\Middleware;

use Closure;
use Fruitcake\Cors\Cors_Service;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
class Handle_Cors
{
    /**
     * The CORS service instance.
     *
     * @var \Fruitcake\Cors\CorsService
     */
    protected $cors;
    /**
     * All of the registered skip callbacks.
     *
     * @var array<int, \Closure(\Illuminate\Http\Request): bool>
     */
    protected static $skip_callbacks = [];
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The container instance.
         */
        protected \Illuminate\Contracts\Container\Container $container,
        Cors_Service $cors
    )
    {
        $this->cors = $cors;
    }
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handle($request, Closure $next)
    {
        foreach (static::$skip_callbacks as $callback) {
            if ($callback($request)) {
                return $next($request);
            }
        }
        if (!$this->has_matching_path($request)) {
            return $next($request);
        }
        $this->cors->set_options($this->container['config']->get('cors', []));
        if ($this->cors->is_preflight_request($request)) {
            $response = $this->cors->handle_preflight_request($request);
            $this->cors->vary_header($response, 'Access-Control-Request-Method');
            return $response;
        }
        $response = $next($request);
        if ($request->get_method() === 'OPTIONS') {
            $this->cors->vary_header($response, 'Access-Control-Request-Method');
        }
        return $this->cors->add_actual_request_headers($response, $request);
    }
    /**
     * Get the path from the configuration to determine if the CORS service should run.
     */
    protected function has_matching_path(Request $request): bool
    {
        $paths = $this->get_paths_by_host($request->get_host());
        foreach ($paths as $path) {
            if ($path !== '/') {
                $path = trim((string) $path, '/');
            }
            if ($request->full_url_is($path) || $request->is($path)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Get the CORS paths for the given host.
     *
     * @return array
     */
    protected function get_paths_by_host(string $host)
    {
        $paths = $this->container['config']->get('cors.paths', []);
        return $paths[$host] ?? array_filter($paths, is_string(...));
    }
    /**
     * Register a callback that instructs the middleware to be skipped.
     */
    public static function skip_when(Closure $callback): void
    {
        static::$skip_callbacks[] = $callback;
    }
    /**
     * Flush the middleware's global state.
     */
    public static function flush_state(): void
    {
        static::$skip_callbacks = [];
    }
}