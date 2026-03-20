<?php

declare (strict_types=1);
namespace Illuminate\Cookie\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\Decrypt_Exception;
use Illuminate\Cookie\Cookie_Value_Prefix;
use Illuminate\Support\Arr;
use Symfony\Component\Http_Foundation\Cookie;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
class Encrypt_Cookies
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [];
    /**
     * The globally ignored cookies that should not be encrypted.
     *
     * @var array
     */
    protected static $never_encrypt = [];
    /**
     * Indicates if cookies should be serialized.
     *
     * @var bool
     */
    protected static $serialize = false;
    /**
     * Create a new CookieGuard instance.
     */
    public function __construct(
        /**
         * The encrypter instance.
         */
        protected \Illuminate\Contracts\Encryption\Encrypter $encrypter
    )
    {
    }
    /**
     * Disable encryption for the given cookie name(s).
     *
     * @param  string|array  $name
     */
    public function disable_for($name): void
    {
        $this->except = array_merge($this->except, (array) $name);
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function handle(\Symfony\Component\Http_Foundation\Request $request, Closure $next): \Symfony\Component\Http_Foundation\Response
    {
        return $this->encrypt($next($this->decrypt($request)));
    }
    /**
     * Decrypt the cookies on the request.
     */
    protected function decrypt(Request $request): Request
    {
        foreach ($request->cookies as $key => $cookie) {
            if ($this->is_disabled($key)) {
                continue;
            }
            try {
                $value = $this->decrypt_cookie($key, $cookie);
                $request->cookies->set($key, $this->validate_value($key, $value));
            } catch (Decrypt_Exception) {
                $request->cookies->set($key, null);
            }
        }
        return $request;
    }
    /**
     * Validate and remove the cookie value prefix from the value.
     *
     * @param  array<string, string>|string  $value
     * @return array|string|null
     * @phpstan-return ($value is array ? array<string|null> : string|null)
     */
    protected function validate_value(string $key, $value)
    {
        return is_array($value) ? $this->validate_array($key, $value) : Cookie_Value_Prefix::validate($key, $value, $this->encrypter->get_all_keys());
    }
    /**
     * Validate and remove the cookie value prefix from all values of an array.
     */
    protected function validate_array(string $key, array $value): array
    {
        $validated = [];
        foreach ($value as $index => $sub_value) {
            $validated[$index] = $this->validate_value("{$key}[{$index}]", $sub_value);
        }
        return $validated;
    }
    /**
     * Decrypt the given cookie and return the value.
     *
     * @param  string  $name
     * @param  string|array  $cookie
     * @return string|array
     */
    protected function decrypt_cookie($name, $cookie)
    {
        return is_array($cookie) ? $this->decrypt_array($cookie) : $this->encrypter->decrypt($cookie, static::serialized($name));
    }
    /**
     * Decrypt an array based cookie.
     */
    protected function decrypt_array(array $cookie): array
    {
        $decrypted = [];
        foreach ($cookie as $key => $value) {
            if (is_string($value)) {
                $decrypted[$key] = $this->encrypter->decrypt($value, static::serialized($key));
            }
            if (is_array($value)) {
                $decrypted[$key] = $this->decrypt_array($value);
            }
        }
        return $decrypted;
    }
    /**
     * Encrypt the cookies on an outgoing response.
     */
    protected function encrypt(Response $response): Response
    {
        foreach ($response->headers->get_cookies() as $cookie) {
            if ($this->is_disabled($cookie->get_name())) {
                continue;
            }
            $response->headers->set_cookie($this->duplicate($cookie, $this->encrypter->encrypt(Cookie_Value_Prefix::create($cookie->get_name(), $this->encrypter->get_key()) . $cookie->get_value(), static::serialized($cookie->get_name()))));
        }
        return $response;
    }
    /**
     * Duplicate a cookie with a new value.
     *
     * @param  mixed  $value
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function duplicate(Cookie $cookie, $value)
    {
        return $cookie->with_value($value);
    }
    /**
     * Determine whether encryption has been disabled for the given cookie.
     *
     * @param  string  $name
     */
    public function is_disabled($name): bool
    {
        return in_array($name, array_merge($this->except, static::$never_encrypt));
    }
    /**
     * Indicate that the given cookies should never be encrypted.
     *
     * @param  array|string  $cookies
     */
    public static function except($cookies): void
    {
        static::$never_encrypt = array_values(array_unique(array_merge(static::$never_encrypt, Arr::wrap($cookies))));
    }
    /**
     * Determine if the cookie contents should be serialized.
     *
     * @param  string  $name
     * @return bool
     */
    public static function serialized($name)
    {
        return static::$serialize;
    }
    /**
     * Flush the middleware's global state.
     */
    public static function flush_state(): void
    {
        static::$never_encrypt = [];
        static::$serialize = false;
    }
}