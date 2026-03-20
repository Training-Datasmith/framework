<?php

declare (strict_types=1);
namespace Illuminate\Auth\Middleware;

use Closure;
use Illuminate\Support\Facades\Date;
class Require_Password
{
    /**
     * The password timeout.
     *
     * @var int
     */
    protected $password_timeout;
    /**
     * Create a new middleware instance.
     *
     * @param  int|null  $passwordTimeout
     */
    public function __construct(
        /**
         * The response factory instance.
         */
        protected \Illuminate\Contracts\Routing\Response_Factory $response_factory,
        /**
         * The URL generator instance.
         */
        protected \Illuminate\Contracts\Routing\Url_Generator $url_generator,
        $password_timeout = null
    )
    {
        $this->password_timeout = $password_timeout ?: 10800;
    }
    /**
     * Specify the redirect route and timeout for the middleware.
     *
     * @param  string|null  $redirectToRoute
     * @param  string|int|null  $passwordTimeoutSeconds
     *
     * @named-arguments-supported
     */
    public static function using($redirect_to_route = null, $password_timeout_seconds = null): string
    {
        return static::class . ':' . implode(',', func_get_args());
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $redirectToRoute
     * @param  string|int|null  $passwordTimeoutSeconds
     * @return mixed
     */
    public function handle($request, Closure $next, $redirect_to_route = null, $password_timeout_seconds = null)
    {
        if ($this->should_confirm_password($request, $password_timeout_seconds)) {
            if ($request->expects_json()) {
                return $this->response_factory->json(['message' => 'Password confirmation required.'], 423);
            }
            return $this->response_factory->redirect_guest($this->url_generator->route($redirect_to_route ?: 'password.confirm'));
        }
        return $next($request);
    }
    /**
     * Determine if the confirmation timeout has expired.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|null  $passwordTimeoutSeconds
     */
    protected function should_confirm_password($request, $password_timeout_seconds = null): bool
    {
        $confirmed_at = Date::now()->unix() - $request->session()->get('auth.password_confirmed_at', 0);
        return $confirmed_at > ($password_timeout_seconds ?? $this->password_timeout);
    }
}