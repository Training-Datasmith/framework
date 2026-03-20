<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Exception;
use Illuminate\Http\Request;
class Authentication_Exception extends Exception
{
    /**
     * The callback that should be used to generate the authentication redirect path.
     *
     * @var callable
     */
    protected static $redirect_to_callback;
    /**
     * Create a new authentication exception.
     *
     * @param  string  $message
     * @param  string|null  $redirectTo
     */
    public function __construct(
        $message = 'Unauthenticated.',
        /**
         * All of the guards that were checked.
         */
        protected array $guards = [],
        /**
         * The path the user should be redirected to.
         */
        protected $redirect_to = null
    )
    {
        parent::__construct($message);
    }
    /**
     * Get the guards that were checked.
     */
    public function guards(): array
    {
        return $this->guards;
    }
    /**
     * Get the path the user should be redirected to.
     *
     * @return string|null
     */
    public function redirect_to(Request $request)
    {
        if ($this->redirect_to) {
            return $this->redirect_to;
        }
        if (static::$redirect_to_callback) {
            return call_user_func(static::$redirect_to_callback, $request);
        }
    }
    /**
     * Specify the callback that should be used to generate the redirect path.
     */
    public static function redirect_using(callable $redirect_to_callback): void
    {
        static::$redirect_to_callback = $redirect_to_callback;
    }
}