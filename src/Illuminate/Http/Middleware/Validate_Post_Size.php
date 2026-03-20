<?php

declare (strict_types=1);
namespace Illuminate\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\Post_Too_Large_Exception;
class Validate_Post_Size
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     * @throws \Illuminate\Http\Exceptions\PostTooLargeException
     */
    public function handle($request, Closure $next)
    {
        $max = $this->get_post_max_size();
        if ($max > 0 && $request->server('CONTENT_LENGTH') > $max) {
            throw new Post_Too_Large_Exception('The POST data is too large.');
        }
        return $next($request);
    }
    /**
     * Determine the server 'post_max_size' as bytes.
     */
    protected function get_post_max_size(): int
    {
        if (is_numeric($post_max_size = ini_get('post_max_size'))) {
            return (int) $post_max_size;
        }
        $metric = strtoupper(substr($post_max_size, -1));
        $post_max_size = (int) $post_max_size;
        return match ($metric) {
            'K' => $post_max_size * 1024,
            'M' => $post_max_size * 1048576,
            'G' => $post_max_size * 1073741824,
            default => $post_max_size,
        };
    }
}