<?php

declare (strict_types=1);
namespace Illuminate\Http\Middleware;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Vite;
class Add_Link_Headers_For_Preloaded_Assets
{
    /**
     * Configure the middleware.
     *
     * @param  int  $limit
     */
    public static function using($limit): string
    {
        return static::class . ':' . $limit;
    }
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|null  $limit
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next, $limit = null)
    {
        return tap($next($request), function ($response) use ($limit): void {
            if ($response instanceof Response && Vite::preloaded_assets() !== []) {
                $response->header('Link', (new Collection(Vite::preloaded_assets()))->when($limit, fn($assets, $limit): \Illuminate\Support\Collection => $assets->take($limit))->map(fn($attributes, $url): string => "<{$url}>; " . implode('; ', $attributes))->join(', '), false);
            }
        });
    }
}