<?php

namespace Illuminate\Auth;

use Exception;
use Illuminate\Http\Request;

class AuthenticationException extends Exception
{
    /**
     * The callback that should be used to generate the authentication redirect path.
     *
     * @var callable
     */
    protected static $redirectToCallback;

    /**
     * Create a new authentication exception.
     *
     * @param  string  $message
     * @param  string|null  $redirectTo
     */
    public function __construct($message = 'Unauthenticated.', /**
     * All of the guards that were checked.
     */
    protected array $guards = [], /**
     * The path the user should be redirected to.
     */
    protected $redirectTo = null)
    {
        parent::__construct($message);
    }

    /**
     * Get the guards that were checked.
     *
     * @return array
     */
    public function guards()
    {
        return $this->guards;
    }

    /**
     * Get the path the user should be redirected to.
     *
     * @return string|null
     */
    public function redirectTo(Request $request)
    {
        if ($this->redirectTo) {
            return $this->redirectTo;
        }

        if (static::$redirectToCallback) {
            return call_user_func(static::$redirectToCallback, $request);
        }
    }

    /**
     * Specify the callback that should be used to generate the redirect path.
     */
    public static function redirectUsing(callable $redirectToCallback): void
    {
        static::$redirectToCallback = $redirectToCallback;
    }
}
