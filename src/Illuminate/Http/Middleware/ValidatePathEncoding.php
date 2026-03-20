<?php

declare (strict_types=1);
namespace Illuminate\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\Malformed_Url_Exception;
use Illuminate\Http\Request;
class Validate_Path_Encoding
{
    /**
     * Validate that the incoming request has a valid UTF-8 encoded path.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $decoded_path = rawurldecode($request->path());
        if (!mb_check_encoding($decoded_path, 'UTF-8')) {
            throw new Malformed_Url_Exception();
        }
        return $next($request);
    }
}