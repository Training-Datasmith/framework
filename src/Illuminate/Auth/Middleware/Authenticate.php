<?php

declare (strict_types=1);
namespace Illuminate\Auth\Middleware;

use Closure;
use Illuminate\Auth\Authentication_Exception;
use Illuminate\Contracts\Auth\Middleware\Authenticates_Requests;
use Illuminate\Http\Request;
class Authenticate implements Authenticates_Requests
{
    /**
     * The callback that should be used to generate the authentication redirect path.
     *
     * @var callable
     */
    protected static $redirect_to_callback;
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The authentication factory instance.
         */
        protected \Illuminate\Contracts\Auth\Factory $auth
    )
    {
    }
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
     * @param  \Illuminate\Http\Request  $request
     * @param  string  ...$guards
     * @return mixed
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);
        return $next($request);
    }
    /**
     * Determine if the user is logged in to any of the given guards.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }
        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $this->auth->should_use($guard);
            }
        }
        $this->unauthenticated($request, $guards);
    }
    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards): never
    {
        throw new Authentication_Exception('Unauthenticated.', $guards, $request->expects_json() ? null : $this->redirect_to($request));
    }
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @return string|null
     */
    protected function redirect_to(Request $request)
    {
        if (static::$redirect_to_callback) {
            return call_user_func(static::$redirect_to_callback, $request);
        }
    }
    /**
     * Specify the callback that should be used to generate the redirect path.
     */
    public static function redirect_using(callable $redirect_to_callback): void
    {
        static::$redirect_to_callback = $redirect_to_callback;
    }
}