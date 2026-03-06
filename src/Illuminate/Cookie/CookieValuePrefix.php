<?php

namespace Illuminate\Cookie;

class CookieValuePrefix
{
    /**
     * Create a new cookie value prefix for the given cookie name.
     *
     * @param  string  $key
     */
    public static function create(string $cookieName, $key): string
    {
        return hash_hmac('sha1', $cookieName.'v2', $key).'|';
    }

    /**
     * Remove the cookie value prefix.
     *
     * @param  string  $cookieValue
     */
    public static function remove($cookieValue): string
    {
        return substr($cookieValue, 41);
    }

    /**
     * Validate a cookie value contains a valid prefix. If it does, return the cookie value with the prefix removed. Otherwise, return null.
     *
     * @param  string  $cookieName
     * @param  string  $cookieValue
     * @return string|null
     */
    public static function validate($cookieName, $cookieValue, array $keys)
    {
        foreach ($keys as $key) {
            $hasValidPrefix = str_starts_with($cookieValue, static::create($cookieName, $key));

            if ($hasValidPrefix) {
                return static::remove($cookieValue);
            }
        }
    }
}
