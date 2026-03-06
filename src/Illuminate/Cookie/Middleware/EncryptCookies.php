<?php

declare(strict_types=1);

namespace Illuminate\Cookie\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EncryptCookies
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
    protected static $neverEncrypt = [];

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
    ) {
    }

    /**
     * Disable encryption for the given cookie name(s).
     *
     * @param  string|array  $name
     */
    public function disableFor($name): void
    {
        $this->except = array_merge($this->except, (array) $name);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function handle(\Symfony\Component\HttpFoundation\Request $request, Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        return $this->encrypt($next($this->decrypt($request)));
    }

    /**
     * Decrypt the cookies on the request.
     */
    protected function decrypt(Request $request): Request
    {
        foreach ($request->cookies as $key => $cookie) {
            if ($this->isDisabled($key)) {
                continue;
            }

            try {
                $value = $this->decryptCookie($key, $cookie);

                $request->cookies->set($key, $this->validateValue($key, $value));
            } catch (DecryptException) {
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
    protected function validateValue(string $key, $value)
    {
        return is_array($value)
            ? $this->validateArray($key, $value)
            : CookieValuePrefix::validate($key, $value, $this->encrypter->getAllKeys());
    }

    /**
     * Validate and remove the cookie value prefix from all values of an array.
     */
    protected function validateArray(string $key, array $value): array
    {
        $validated = [];

        foreach ($value as $index => $subValue) {
            $validated[$index] = $this->validateValue("{$key}[{$index}]", $subValue);
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
    protected function decryptCookie($name, $cookie)
    {
        return is_array($cookie)
            ? $this->decryptArray($cookie)
            : $this->encrypter->decrypt($cookie, static::serialized($name));
    }

    /**
     * Decrypt an array based cookie.
     */
    protected function decryptArray(array $cookie): array
    {
        $decrypted = [];

        foreach ($cookie as $key => $value) {
            if (is_string($value)) {
                $decrypted[$key] = $this->encrypter->decrypt($value, static::serialized($key));
            }

            if (is_array($value)) {
                $decrypted[$key] = $this->decryptArray($value);
            }
        }

        return $decrypted;
    }

    /**
     * Encrypt the cookies on an outgoing response.
     */
    protected function encrypt(Response $response): Response
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($this->isDisabled($cookie->getName())) {
                continue;
            }

            $response->headers->setCookie($this->duplicate(
                $cookie,
                $this->encrypter->encrypt(
                    CookieValuePrefix::create($cookie->getName(), $this->encrypter->getKey()).$cookie->getValue(),
                    static::serialized($cookie->getName())
                )
            ));
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
        return $cookie->withValue($value);
    }

    /**
     * Determine whether encryption has been disabled for the given cookie.
     *
     * @param  string  $name
     */
    public function isDisabled($name): bool
    {
        return in_array($name, array_merge($this->except, static::$neverEncrypt));
    }

    /**
     * Indicate that the given cookies should never be encrypted.
     *
     * @param  array|string  $cookies
     */
    public static function except($cookies): void
    {
        static::$neverEncrypt = array_values(array_unique(
            array_merge(static::$neverEncrypt, Arr::wrap($cookies))
        ));
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
    public static function flushState(): void
    {
        static::$neverEncrypt = [];

        static::$serialize = false;
    }
}
