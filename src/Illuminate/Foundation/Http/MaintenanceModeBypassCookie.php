<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http;

use Illuminate\Support\Carbon;
use Symfony\Component\Http_Foundation\Cookie;
class Maintenance_Mode_Bypass_Cookie
{
    /**
     * Create a new maintenance mode bypass cookie.
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public static function create(string $key)
    {
        $expires_at = Carbon::now()->add_hours(12);
        return new Cookie('laravel_maintenance', base64_encode(json_encode(['expires_at' => $expires_at->get_timestamp(), 'mac' => hash_hmac('sha256', (string) $expires_at->get_timestamp(), $key)])), $expires_at, config('session.path'), config('session.domain'));
    }
    /**
     * Determine if the given maintenance mode bypass cookie is valid.
     */
    public static function is_valid(string $cookie, string $key): bool
    {
        $payload = json_decode(base64_decode($cookie), true);
        return is_array($payload) && is_numeric($payload['expires_at'] ?? null) && isset($payload['mac']) && hash_equals(hash_hmac('sha256', $payload['expires_at'], $key), $payload['mac']) && (int) $payload['expires_at'] >= Carbon::now()->get_timestamp();
    }
}