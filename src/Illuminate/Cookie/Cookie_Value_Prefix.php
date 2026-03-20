<?php

declare (strict_types=1);
namespace Illuminate\Cookie;

class Cookie_Value_Prefix
{
    /**
     * Create a new cookie value prefix for the given cookie name.
     *
     * @param  string  $key
     */
    public static function create(string $cookie_name, $key): string
    {
        return hash_hmac('sha1', $cookie_name . 'v2', $key) . '|';
    }
    /**
     * Remove the cookie value prefix.
     *
     * @param  string  $cookieValue
     */
    public static function remove($cookie_value): string
    {
        return substr($cookie_value, 41);
    }
    /**
     * Validate a cookie value contains a valid prefix. If it does, return the cookie value with the prefix removed. Otherwise, return null.
     *
     * @param  string  $cookieValue
     * @return string|null
     */
    public static function validate(string $cookie_name, $cookie_value, array $keys)
    {
        foreach ($keys as $key) {
            $has_valid_prefix = str_starts_with($cookie_value, static::create($cookie_name, $key));
            if ($has_valid_prefix) {
                return static::remove($cookie_value);
            }
        }
    }
}