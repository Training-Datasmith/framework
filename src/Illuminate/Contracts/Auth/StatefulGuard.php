<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Auth;

interface Stateful_Guard extends Guard
{
    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param  bool  $remember
     * @return bool
     */
    public function attempt(array $credentials = [], $remember = false);
    /**
     * Log a user into the application without sessions or cookies.
     *
     * @return bool
     */
    public function once(array $credentials = []);
    /**
     * Log a user into the application.
     *
     * @param  bool  $remember
     * @return void
     */
    public function login(Authenticatable $user, $remember = false);
    /**
     * Log the given user ID into the application.
     *
     * @param  mixed  $id
     * @param  bool  $remember
     * @return \Illuminate\Contracts\Auth\Authenticatable|false
     */
    public function login_using_id($id, $remember = false);
    /**
     * Log the given user ID into the application without sessions or cookies.
     *
     * @param  mixed  $id
     * @return \Illuminate\Contracts\Auth\Authenticatable|false
     */
    public function once_using_id($id);
    /**
     * Determine if the user was authenticated via "remember me" cookie.
     *
     * @return bool
     */
    public function via_remember();
    /**
     * Log the user out of the application.
     *
     * @return void
     */
    public function logout();
}