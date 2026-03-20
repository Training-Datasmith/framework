<?php

declare (strict_types=1);
namespace Illuminate\Auth\Passwords;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Auth\Can_Reset_Password as CanResetPasswordContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
class Cache_Token_Repository implements Token_Repository_Interface
{
    /**
     * The format of the stored Carbon object.
     */
    protected string $format = 'Y-m-d H:i:s';
    /**
     * Create a new token repository instance.
     */
    public function __construct(protected Repository $cache, protected Hasher_Contract $hasher, protected string $hash_key, protected int $expires = 3600, protected int $throttle = 60)
    {
    }
    /**
     * Create a new token.
     */
    public function create(Can_Reset_Password_Contract $user): string
    {
        $this->delete($user);
        $token = hash_hmac('sha256', Str::random(40), $this->hash_key);
        $this->cache->put($this->cache_key($user), [$this->hasher->make($token), Carbon::now()->format($this->format)], $this->expires);
        return $token;
    }
    /**
     * Determine if a token record exists and is valid.
     *
     * @param  string  $token
     */
    public function exists(
        Can_Reset_Password_Contract $user,
        #[\Sensitive_Parameter]
        $token
    ): bool
    {
        [$record, $created_at] = $this->cache->get($this->cache_key($user));
        return $record && !$this->token_expired($created_at) && $this->hasher->check($token, $record);
    }
    /**
     * Determine if the token has expired.
     *
     * @param  string  $createdAt
     * @return bool
     */
    protected function token_expired($created_at)
    {
        return Carbon::create_from_format($this->format, $created_at)->add_seconds($this->expires)->is_past();
    }
    /**
     * Determine if the given user recently created a password reset token.
     */
    public function recently_created_token(Can_Reset_Password_Contract $user): bool
    {
        [$record, $created_at] = $this->cache->get($this->cache_key($user));
        return $record && $this->token_recently_created($created_at);
    }
    /**
     * Determine if the token was recently created.
     *
     * @param  string  $createdAt
     * @return bool
     */
    protected function token_recently_created($created_at)
    {
        if ($this->throttle <= 0) {
            return false;
        }
        return Carbon::create_from_format($this->format, $created_at)->add_seconds($this->throttle)->is_future();
    }
    /**
     * Delete a token record.
     */
    public function delete(Can_Reset_Password_Contract $user): void
    {
        $this->cache->forget($this->cache_key($user));
    }
    /**
     * Delete expired tokens.
     *
     * @return void
     */
    public function delete_expired()
    {
    }
    /**
     * Determine the cache key for the given user.
     */
    public function cache_key(Can_Reset_Password_Contract $user): string
    {
        return hash('sha256', $user->get_email_for_password_reset());
    }
}