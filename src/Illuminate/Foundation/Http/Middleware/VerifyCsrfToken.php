<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\Decrypt_Exception;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Cookie\Cookie_Value_Prefix;
use Illuminate\Cookie\Middleware\Encrypt_Cookies;
use Illuminate\Foundation\Http\Middleware\Concerns\Excludes_Paths;
use Illuminate\Session\Token_Mismatch_Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Interacts_With_Time;
use Symfony\Component\Http_Foundation\Cookie;
class Verify_Csrf_Token
{
    use Interacts_With_Time;
    use Excludes_Paths;
    /**
     * The URIs that should be excluded.
     *
     * @var array<int, string>
     */
    protected $except = [];
    /**
     * The globally ignored URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected static $never_verify = [];
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $add_http_cookie = true;
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The application instance.
         */
        protected \Illuminate\Contracts\Foundation\Application $app,
        /**
         * The encrypter implementation.
         */
        protected \Illuminate\Contracts\Encryption\Encrypter $encrypter
    )
    {
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     * @throws \Illuminate\Session\TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        if ($this->is_reading($request) || $this->running_unit_tests() || $this->in_except_array($request) || $this->tokens_match($request)) {
            return tap($next($request), function ($response) use ($request): void {
                if ($this->should_add_xsrf_token_cookie()) {
                    $this->add_cookie_to_response($request, $response);
                }
            });
        }
        throw new Token_Mismatch_Exception('CSRF token mismatch.');
    }
    /**
     * Determine if the HTTP request uses a ‘read’ verb.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function is_reading($request): bool
    {
        return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS']);
    }
    /**
     * Determine if the application is running unit tests.
     */
    protected function running_unit_tests(): bool
    {
        return $this->app->running_in_console() && $this->app->running_unit_tests();
    }
    /**
     * Get the URIs that should be excluded.
     */
    public function get_excluded_paths(): array
    {
        return array_merge($this->except, static::$never_verify);
    }
    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function tokens_match($request): bool
    {
        $token = $this->get_token_from_request($request);
        return is_string($request->session()->token()) && is_string($token) && hash_equals($request->session()->token(), $token);
    }
    /**
     * Get the CSRF token from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function get_token_from_request($request)
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
        if (!$token && $header = $request->header('X-XSRF-TOKEN')) {
            try {
                $token = Cookie_Value_Prefix::remove($this->encrypter->decrypt($header, static::serialized()));
            } catch (Decrypt_Exception) {
                $token = '';
            }
        }
        return $token;
    }
    /**
     * Determine if the cookie should be added to the response.
     *
     * @return bool
     */
    public function should_add_xsrf_token_cookie()
    {
        return $this->add_http_cookie;
    }
    /**
     * Add the CSRF token to the response cookies.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function add_cookie_to_response($request, $response)
    {
        $config = config('session');
        if ($response instanceof Responsable) {
            $response = $response->to_response($request);
        }
        $response->headers->set_cookie($this->new_cookie($request, $config));
        return $response;
    }
    /**
     * Create a new "XSRF-TOKEN" cookie that contains the CSRF token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function new_cookie($request, array $config)
    {
        return new Cookie('XSRF-TOKEN', $request->session()->token(), $this->available_at(60 * $config['lifetime']), $config['path'], $config['domain'], $config['secure'], false, false, $config['same_site'] ?? null, $config['partitioned'] ?? false);
    }
    /**
     * Indicate that the given URIs should be excluded from CSRF verification.
     *
     * @param  array|string  $uris
     */
    public static function except($uris): void
    {
        static::$never_verify = array_values(array_unique(array_merge(static::$never_verify, Arr::wrap($uris))));
    }
    /**
     * Determine if the cookie contents should be serialized.
     *
     * @return bool
     */
    public static function serialized()
    {
        return Encrypt_Cookies::serialized('XSRF-TOKEN');
    }
    /**
     * Flush the state of the middleware.
     */
    public static function flush_state(): void
    {
        static::$never_verify = [];
    }
}