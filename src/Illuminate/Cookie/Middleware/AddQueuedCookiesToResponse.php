<?php

declare(strict_types=1);

namespace Illuminate\Cookie\Middleware;

use Closure;

class AddQueuedCookiesToResponse
{
    /**
     * Create a new CookieQueue instance.
     */
    public function __construct(
        /**
         * The cookie jar instance.
         */
        protected \Illuminate\Contracts\Cookie\QueueingFactory $cookies
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        foreach ($this->cookies->getQueuedCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
