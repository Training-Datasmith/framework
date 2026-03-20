<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\User_Provider;
/**
 * These methods are typically the same across all guards.
 */
trait Guard_Helpers
{
    /**
     * The currently authenticated user.
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected $user;
    /**
     * The user provider implementation.
     *
     * @var \Illuminate\Contracts\Auth\UserProvider
     */
    protected $provider;
    /**
     * Determine if the current user is authenticated. If not, throw an exception.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function authenticate()
    {
        return $this->user() ?? throw new Authentication_Exception();
    }
    /**
     * Determine if the guard has a user instance.
     */
    public function has_user(): bool
    {
        return !is_null($this->user);
    }
    /**
     * Determine if the current user is authenticated.
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }
    /**
     * Determine if the current user is a guest.
     */
    public function guest(): bool
    {
        return !$this->check();
    }
    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|string|null
     */
    public function id()
    {
        return $this->user()?->get_auth_identifier();
    }
    /**
     * Set the current user.
     *
     * @return $this
     */
    public function set_user(Authenticatable_Contract $user)
    {
        $this->user = $user;
        return $this;
    }
    /**
     * Forget the current user.
     *
     * @return $this
     */
    public function forget_user()
    {
        $this->user = null;
        return $this;
    }
    /**
     * Get the user provider used by the guard.
     *
     * @return \Illuminate\Contracts\Auth\UserProvider
     */
    public function get_provider()
    {
        return $this->provider;
    }
    /**
     * Set the user provider used by the guard.
     */
    public function set_provider(User_Provider $provider): void
    {
        $this->provider = $provider;
    }
}