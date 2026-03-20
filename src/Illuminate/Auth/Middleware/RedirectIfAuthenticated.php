<?php

declare (strict_types=1);
namespace Illuminate\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Http_Foundation\Response;
class Redirect_If_Authenticated
{
    /**
     * The callback that should be used to generate the authentication redirect path.
     *
     * @var callable|null
     */
    protected static $redirect_to_callback;
    /**
     * Specify the guards for the middleware.
     *
     * @param  string  $guard
     * @param  string  $others
     */
    public static function using($guard, ...$others): string
    {
        return static::class . ':' . implode(',', [$guard, ...$others]);
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect($this->redirect_to($request));
            }
        }
        return $next($request);
    }
    /**
     * Get the path the user should be redirected to when they are authenticated.
     */
    protected function redirect_to(Request $request): ?string
    {
        return static::$redirect_to_callback ? call_user_func(static::$redirect_to_callback, $request) : $this->default_redirect_uri();
    }
    /**
     * Get the default URI the user should be redirected to when they are authenticated.
     */
    protected function default_redirect_uri(): string
    {
        foreach (['dashboard', 'home'] as $uri) {
            if (Route::has($uri)) {
                return route($uri);
            }
        }
        $routes = Route::get_routes()->get('GET');
        foreach (['dashboard', 'home'] as $uri) {
            if (isset($routes[$uri])) {
                return '/' . $uri;
            }
        }
        return '/';
    }
    /**
     * Specify the callback that should be used to generate the redirect path.
     */
    public static function redirect_using(callable $redirect_to_callback): void
    {
        static::$redirect_to_callback = $redirect_to_callback;
    }
}