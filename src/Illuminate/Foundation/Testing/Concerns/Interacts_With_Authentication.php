<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
trait Interacts_With_Authentication
{
    /**
     * Set the currently logged in user for the application.
     *
     * @param  string|null  $guard
     * @return $this
     */
    public function acting_as(User_Contract $user, $guard = null)
    {
        return $this->be($user, $guard);
    }
    /**
     * Clear the currently logged in user for the application.
     *
     * @param  string|null  $guard
     * @return $this
     */
    public function acting_as_guest($guard = null)
    {
        $this->app['auth']->guard($guard)->forget_user();
        $this->app['auth']->should_use($guard);
        return $this;
    }
    /**
     * Set the currently logged in user for the application.
     *
     * @param  string|null  $guard
     * @return $this
     */
    public function be(User_Contract $user, $guard = null)
    {
        if (isset($user->was_recently_created) && $user->was_recently_created) {
            $user->was_recently_created = false;
        }
        $this->app['auth']->guard($guard)->set_user($user);
        $this->app['auth']->should_use($guard);
        return $this;
    }
    /**
     * Assert that the user is authenticated.
     *
     * @param  string|null  $guard
     * @return $this
     */
    public function assert_authenticated($guard = null)
    {
        $this->assert_true($this->is_authenticated($guard), 'The user is not authenticated');
        return $this;
    }
    /**
     * Assert that the user is not authenticated.
     *
     * @param  string|null  $guard
     * @return $this
     */
    public function assert_guest($guard = null)
    {
        $this->assert_false($this->is_authenticated($guard), 'The user is authenticated');
        return $this;
    }
    /**
     * Return true if the user is authenticated, false otherwise.
     *
     * @param  string|null  $guard
     * @return bool
     */
    protected function is_authenticated($guard = null)
    {
        return $this->app->make('auth')->guard($guard)->check();
    }
    /**
     * Assert that the user is authenticated as the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string|null  $guard
     * @return $this
     */
    public function assert_authenticated_as($user, $guard = null)
    {
        $expected = $this->app->make('auth')->guard($guard)->user();
        $this->assert_not_null($expected, 'The current user is not authenticated.');
        $this->assert_instance_of($expected::class, $user, 'The currently authenticated user is not who was expected');
        $this->assert_same($expected->get_auth_identifier(), $user->get_auth_identifier(), 'The currently authenticated user is not who was expected');
        return $this;
    }
    /**
     * Assert that the given credentials are valid.
     *
     * @param  string|null  $guard
     * @return $this
     */
    public function assert_credentials(array $credentials, $guard = null)
    {
        $this->assert_true($this->has_credentials($credentials, $guard), 'The given credentials are invalid.');
        return $this;
    }
    /**
     * Assert that the given credentials are invalid.
     *
     * @param  string|null  $guard
     * @return $this
     */
    public function assert_invalid_credentials(array $credentials, $guard = null)
    {
        $this->assert_false($this->has_credentials($credentials, $guard), 'The given credentials are valid.');
        return $this;
    }
    /**
     * Return true if the credentials are valid, false otherwise.
     *
     * @param  string|null  $guard
     */
    protected function has_credentials(array $credentials, $guard = null): bool
    {
        $provider = $this->app->make('auth')->guard($guard)->get_provider();
        $user = $provider->retrieve_by_credentials($credentials);
        return $user && $provider->validate_credentials($user, $credentials);
    }
}