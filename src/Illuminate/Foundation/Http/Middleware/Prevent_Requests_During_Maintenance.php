<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http\Middleware;

use Closure;
use ErrorException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Maintenance_Mode_Bypass_Cookie;
use Illuminate\Foundation\Http\Middleware\Concerns\Excludes_Paths;
use Illuminate\Support\Arr;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
class Prevent_Requests_During_Maintenance
{
    use Excludes_Paths;
    /**
     * The URIs that should be excluded.
     *
     * @var array<int, string>
     */
    protected $except = [];
    /**
     * The URIs that should be accessible during maintenance.
     *
     * @var array
     */
    protected static $never_prevent = [];
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The application implementation.
         */
        protected \Illuminate\Contracts\Foundation\Application $app
    )
    {
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \ErrorException
     */
    public function handle($request, Closure $next)
    {
        if ($this->in_except_array($request)) {
            return $next($request);
        }
        if ($this->app->maintenance_mode()->active()) {
            try {
                $data = $this->app->maintenance_mode()->data();
            } catch (ErrorException $exception) {
                if (!$this->app->maintenance_mode()->active()) {
                    return $next($request);
                }
                throw $exception;
            }
            if (isset($data['secret']) && $request->path() === $data['secret']) {
                return $this->bypass_response($data['secret']);
            }
            if ($this->has_valid_bypass_cookie($request, $data)) {
                return $next($request);
            }
            if (isset($data['redirect'])) {
                $path = $data['redirect'] === '/' ? $data['redirect'] : trim((string) $data['redirect'], '/');
                if ($request->path() !== $path) {
                    return redirect($path);
                }
            }
            if (isset($data['template'])) {
                return response($data['template'], $data['status'] ?? 503, $this->get_headers($data));
            }
            throw new Http_Exception($data['status'] ?? 503, 'Service Unavailable', null, $this->get_headers($data));
        }
        return $next($request);
    }
    /**
     * Determine if the incoming request has a maintenance mode bypass cookie.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function has_valid_bypass_cookie($request, array $data): bool
    {
        return isset($data['secret']) && $request->cookie('laravel_maintenance') && Maintenance_Mode_Bypass_Cookie::is_valid($request->cookie('laravel_maintenance'), $data['secret']);
    }
    /**
     * Redirect the user to their intended destination with a maintenance mode bypass cookie.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function bypass_response(string $secret)
    {
        return redirect()->intended('/')->with_cookie(Maintenance_Mode_Bypass_Cookie::create($secret));
    }
    /**
     * Get the headers that should be sent with the response.
     */
    protected function get_headers(array $data): array
    {
        $headers = isset($data['retry']) ? ['Retry-After' => $data['retry']] : [];
        if (isset($data['refresh'])) {
            $headers['Refresh'] = $data['refresh'];
        }
        return $headers;
    }
    /**
     * Get the URIs that should be excluded.
     */
    public function get_excluded_paths(): array
    {
        return array_merge($this->except, static::$never_prevent);
    }
    /**
     * Indicate that the given URIs should always be accessible.
     *
     * @param  array|string  $uris
     */
    public static function except($uris): void
    {
        static::$never_prevent = array_values(array_unique(array_merge(static::$never_prevent, Arr::wrap($uris))));
    }
    /**
     * Flush the state of the middleware.
     */
    public static function flush_state(): void
    {
        static::$never_prevent = [];
    }
}