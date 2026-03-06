<?php

declare(strict_types=1);

namespace Illuminate\Auth\Middleware;

use Closure;

class AuthenticateWithBasicAuth
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The guard factory instance.
         */
        protected \Illuminate\Contracts\Auth\Factory $auth
    ) {
    }

    /**
     * Specify the guard and field for the middleware.
     *
     * @param  string|null  $guard
     * @param  string|null  $field
     *
     * @named-arguments-supported
     */
    public static function using($guard = null, $field = null): string
    {
        return static::class.':'.implode(',', func_get_args());
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $guard
     * @param  string|null  $field
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     */
    public function handle($request, Closure $next, $guard = null, $field = null)
    {
        $this->auth->guard($guard)->basic($field ?: 'email');

        return $next($request);
    }
}
