<?php

declare (strict_types=1);
namespace Illuminate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
class Trust_Proxies
{
    /**
     * The trusted proxies for the application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;
    /**
     * The trusted proxies headers for the application.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PREFIX | Request::HEADER_X_FORWARDED_AWS_ELB;
    /**
     * The proxies that have been configured to always be trusted.
     *
     * @var array<int, string>|string|null
     */
    protected static $always_trust_proxies;
    /**
     * The proxies headers that have been configured to always be trusted.
     *
     * @var int|null
     */
    protected static $always_trust_headers;
    /**
     * Handle an incoming request.
     *
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle(Request $request, Closure $next)
    {
        $request::set_trusted_proxies([], $this->get_trusted_header_names());
        $this->set_trusted_proxy_ip_addresses($request);
        return $next($request);
    }
    /**
     * Sets the trusted proxies on the request.
     *
     * @return void
     */
    protected function set_trusted_proxy_ip_addresses(Request $request)
    {
        $trusted_ips = $this->proxies() ?: config('trustedproxy.proxies');
        if (is_null($trusted_ips) && (laravel_cloud() || str_ends_with($request->host(), '.on-forge.com') || str_ends_with($request->host(), '.on-vapor.com'))) {
            $trusted_ips = '*';
        }
        if (str_ends_with($request->host(), '.on-forge.com') || str_ends_with($request->host(), '.on-vapor.com')) {
            $request->headers->remove('X-Forwarded-Host');
        }
        if ($trusted_ips === '*' || $trusted_ips === '**') {
            return $this->set_trusted_proxy_ip_addresses_to_the_calling_ip($request);
        }
        $trusted_ips = is_string($trusted_ips) ? array_map(trim(...), explode(',', $trusted_ips)) : $trusted_ips;
        if (is_array($trusted_ips)) {
            return $this->set_trusted_proxy_ip_addresses_to_specific_ips($request, $trusted_ips);
        }
    }
    /**
     * Specify the IP addresses to trust explicitly.
     *
     * @return void
     */
    protected function set_trusted_proxy_ip_addresses_to_specific_ips(Request $request, array $trusted_ips)
    {
        $request->set_trusted_proxies(array_reduce($trusted_ips, function ($ips, $trusted_ip) use ($request) {
            $ips[] = $trusted_ip === 'REMOTE_ADDR' ? $request->server->get('REMOTE_ADDR') : $trusted_ip;
            return $ips;
        }, []), $this->get_trusted_header_names());
    }
    /**
     * Set the trusted proxy to be the IP address calling this servers.
     *
     * @return void
     */
    protected function set_trusted_proxy_ip_addresses_to_the_calling_ip(Request $request)
    {
        $request->set_trusted_proxies([$request->server->get('REMOTE_ADDR')], $this->get_trusted_header_names());
    }
    /**
     * Retrieve trusted header name(s), falling back to defaults if config not set.
     *
     * @return int A bit field of Request::HEADER_*, to set which headers to trust from your proxies.
     */
    protected function get_trusted_header_names()
    {
        $headers = $this->headers();
        if (is_int($headers)) {
            return $headers;
        }
        return match ($headers) {
            'HEADER_X_FORWARDED_AWS_ELB' => Request::HEADER_X_FORWARDED_AWS_ELB,
            'HEADER_FORWARDED' => Request::HEADER_FORWARDED,
            'HEADER_X_FORWARDED_FOR' => Request::HEADER_X_FORWARDED_FOR,
            'HEADER_X_FORWARDED_HOST' => Request::HEADER_X_FORWARDED_HOST,
            'HEADER_X_FORWARDED_PORT' => Request::HEADER_X_FORWARDED_PORT,
            'HEADER_X_FORWARDED_PROTO' => Request::HEADER_X_FORWARDED_PROTO,
            'HEADER_X_FORWARDED_PREFIX' => Request::HEADER_X_FORWARDED_PREFIX,
            default => Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PREFIX | Request::HEADER_X_FORWARDED_AWS_ELB,
        };
    }
    /**
     * Get the trusted headers.
     *
     * @return int
     */
    protected function headers()
    {
        return static::$always_trust_headers ?: $this->headers;
    }
    /**
     * Get the trusted proxies.
     *
     * @return array|string|null
     */
    protected function proxies()
    {
        return static::$always_trust_proxies ?: $this->proxies;
    }
    /**
     * Specify the IP addresses of proxies that should always be trusted.
     */
    public static function at(array|string $proxies): void
    {
        static::$always_trust_proxies = $proxies;
    }
    /**
     * Specify the proxy headers that should always be trusted.
     */
    public static function with_headers(int $headers): void
    {
        static::$always_trust_headers = $headers;
    }
    /**
     * Flush the state of the middleware.
     */
    public static function flush_state(): void
    {
        static::$always_trust_headers = null;
        static::$always_trust_proxies = null;
    }
}