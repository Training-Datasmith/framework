<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Auth;

interface Guard
{
    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check();
    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest();
    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user();
    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|string|null
     */
    public function id();
    /**
     * Validate a user's credentials.
     *
     * @return bool
     */
    public function validate(array $credentials = []);
    /**
     * Determine if the guard has a user instance.
     *
     * @return bool
     */
    public function has_user();
    /**
     * Set the current user.
     *
     * @return $this
     */
    public function set_user(Authenticatable $user);
}