<?php

declare (strict_types=1);
namespace Illuminate\Auth\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Must_Verify_Email;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
class Ensure_Email_Is_Verified
{
    /**
     * Specify the redirect route for the middleware.
     */
    public static function redirect_to(string $route): string
    {
        return static::class . ':' . $route;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $redirectToRoute
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|null
     */
    public function handle($request, Closure $next, $redirect_to_route = null)
    {
        if (!$request->user() || $request->user() instanceof Must_Verify_Email && !$request->user()->has_verified_email()) {
            return $request->expects_json() ? abort(403, 'Your email address is not verified.') : Redirect::guest(URL::route($redirect_to_route ?: 'verification.notice'));
        }
        return $next($request);
    }
}