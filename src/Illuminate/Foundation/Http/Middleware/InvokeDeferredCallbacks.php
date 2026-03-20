<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http\Middleware;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\Deferred_Callback_Collection;
use Symfony\Component\Http_Foundation\Response;
class Invoke_Deferred_Callbacks
{
    /**
     * Handle the incoming request.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
    /**
     * Invoke the deferred callbacks.
     */
    public function terminate(Request $request, Response $response): void
    {
        Container::get_instance()->make(Deferred_Callback_Collection::class)->invoke_when(fn($callback): bool => $response->get_status_code() < 400 || $callback->always);
    }
}