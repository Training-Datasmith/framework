<?php

declare (strict_types=1);
namespace Illuminate\Auth\Passwords;

use Closure;
use Illuminate\Auth\Events\Password_Reset_Link_Sent;
use Illuminate\Contracts\Auth\Can_Reset_Password as CanResetPasswordContract;
use Illuminate\Contracts\Auth\Password_Broker as PasswordBrokerContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Timebox;
use UnexpectedValueException;
class Password_Broker implements Password_Broker_Contract
{
    /**
     * The timebox instance.
     */
    protected \Illuminate\Support\Timebox $timebox;
    /**
     * Create a new password broker instance.
     */
    public function __construct(
        /**
         * The password token repository.
         */
        #[\Sensitive_Parameter]
        protected \Illuminate\Auth\Passwords\Token_Repository_Interface $tokens,
        /**
         * The user provider implementation.
         */
        protected \Illuminate\Contracts\Auth\User_Provider $users,
        /**
         * The event dispatcher instance.
         */
        protected ?\Illuminate\Contracts\Events\Dispatcher $events = null,
        ?Timebox $timebox = null,
        /**
         * The number of microseconds that the timebox should wait for.
         */
        protected int $timebox_duration = 200000
    )
    {
        $this->timebox = $timebox ?: new Timebox();
    }
    /**
     * Send a password reset link to a user.
     *
     * @return string
     */
    public function send_reset_link(
        #[\Sensitive_Parameter]
        array $credentials,
        ?Closure $callback = null
    )
    {
        return $this->timebox->call(function () use ($credentials, $callback) {
            // First we will check to see if we found a user at the given credentials and
            // if we did not we will redirect back to this current URI with a piece of
            // "flash" data in the session to indicate to the developers the errors.
            $user = $this->get_user($credentials);
            if (is_null($user)) {
                return static::INVALID_USER;
            }
            if ($this->tokens->recently_created_token($user)) {
                return static::RESET_THROTTLED;
            }
            $token = $this->tokens->create($user);
            if ($callback) {
                return $callback($user, $token) ?? static::RESET_LINK_SENT;
            }
            // Once we have the reset token, we are ready to send the message out to this
            // user with a link to reset their password. We will then redirect back to
            // the current URI having nothing set in the session to indicate errors.
            $user->send_password_reset_notification($token);
            $this->events?->dispatch(new Password_Reset_Link_Sent($user));
            return static::RESET_LINK_SENT;
        }, $this->timebox_duration);
    }
    /**
     * Reset the password for the given token.
     *
     * @return string
     */
    public function reset(
        #[\Sensitive_Parameter]
        array $credentials,
        Closure $callback
    )
    {
        return $this->timebox->call(function ($timebox) use ($credentials, $callback) {
            $user = $this->validate_reset($credentials);
            // If the responses from the validate method is not a user instance, we will
            // assume that it is a redirect and simply return it from this method and
            // the user is properly redirected having an error message on the post.
            if (!$user instanceof Can_Reset_Password_Contract) {
                return $user;
            }
            $password = $credentials['password'];
            // Once the reset has been validated, we'll call the given callback with the
            // new password. This gives the user an opportunity to store the password
            // in their persistent storage. Then we'll delete the token and return.
            $callback($user, $password);
            $this->tokens->delete($user);
            $timebox->return_early();
            return static::PASSWORD_RESET;
        }, $this->timebox_duration);
    }
    /**
     * Validate a password reset for the given credentials.
     */
    protected function validate_reset(
        #[\Sensitive_Parameter]
        array $credentials
    ): string|\Illuminate\Contracts\Auth\Can_Reset_Password
    {
        if (is_null($user = $this->get_user($credentials))) {
            return static::INVALID_USER;
        }
        if (!$this->tokens->exists($user, $credentials['token'])) {
            return static::INVALID_TOKEN;
        }
        return $user;
    }
    /**
     * Get the user for the given credentials.
     *
     *
     * @throws \UnexpectedValueException
     */
    public function get_user(
        #[\Sensitive_Parameter]
        array $credentials
    ): ?\Illuminate\Contracts\Auth\Can_Reset_Password
    {
        $credentials = Arr::except($credentials, ['token']);
        $user = $this->users->retrieve_by_credentials($credentials);
        if ($user && !$user instanceof Can_Reset_Password_Contract) {
            throw new UnexpectedValueException('User must implement CanResetPassword interface.');
        }
        return $user;
    }
    /**
     * Create a new password reset token for the given user.
     *
     * @return string
     */
    public function create_token(Can_Reset_Password_Contract $user)
    {
        return $this->tokens->create($user);
    }
    /**
     * Delete password reset tokens of the given user.
     */
    public function delete_token(Can_Reset_Password_Contract $user): void
    {
        $this->tokens->delete($user);
    }
    /**
     * Validate the given password reset token.
     *
     * @param  string  $token
     * @return bool
     */
    public function token_exists(
        Can_Reset_Password_Contract $user,
        #[\Sensitive_Parameter]
        $token
    )
    {
        return $this->tokens->exists($user, $token);
    }
    /**
     * Get the password reset token repository implementation.
     */
    public function get_repository(): \Illuminate\Auth\Passwords\Token_Repository_Interface
    {
        return $this->tokens;
    }
    /**
     * Get the timebox instance used by the guard.
     */
    public function get_timebox(): \Illuminate\Support\Timebox
    {
        return $this->timebox;
    }
}