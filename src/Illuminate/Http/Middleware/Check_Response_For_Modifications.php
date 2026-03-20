<?php

declare (strict_types=1);
namespace Illuminate\Http\Middleware;

use Closure;
use Symfony\Component\Http_Foundation\Response;
class Check_Response_For_Modifications
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if ($response instanceof Response) {
            $response->is_not_modified($request);
        }
        return $response;
    }
}