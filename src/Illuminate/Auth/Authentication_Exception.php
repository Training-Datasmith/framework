<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Exception;
use Illuminate\Http\Request;

/**
 * Thrown when an incoming request is not authenticated.
 *
 * Carries the list of auth guards that were attempted so that middleware and
 * exception handlers can determine which login page to redirect to or what
 * error response to return (e.g. JSON 401 for API requests).
 *
 * @since 5.2
 */
class Authentication_Exception extends Exception
{
    /**
     * The callback that should be used to generate the authentication redirect path.
     *
     * @var callable|null
     */
    protected static $redirect_to_callback;

    /**
     * Create a new authentication exception.
     *
     * @param  string        $message      Human-readable description of the failure (default: 'Unauthenticated.').
     * @param  string[]      $guards       Names of the auth guards that were checked before throwing.
     * @param  string|null   $redirect_to  Explicit redirect URL; null defers to the registered callback.
     *
     * @since 5.2
     */
    public function __construct(
        string $message = 'Unauthenticated.',
        /**
         * All of the guards that were checked before throwing.
         */
        protected array $guards = [],
        /**
         * The explicit path the user should be redirected to, or null to use the callback.
         */
        protected ?string $redirect_to = null
    ) {
        parent::__construct($message);
    }

    /**
     * Get the guards that were checked.
     *
     * @return string[]  Names of all guards that were tried before the exception was raised.
     *
     * @since 5.2
     */
    public function guards(): array
    {
        return $this->guards;
    }

    /**
     * Get the path the user should be redirected to.
     *
     * Resolution order:
     * 1. The explicit $redirect_to URL set on this exception instance.
     * 2. The return value of the registered {@see redirect_using()} callback.
     * 3. `null` — the caller must decide where to send the user.
     *
     * @param  Request      $request  The current HTTP request, passed to the redirect callback.
     * @return string|null            Redirect URL, or null if no redirect target is configured.
     *
     * @since 5.2
     */
    public function redirect_to(Request $request): ?string
    {
        if ($this->redirect_to) {
            return $this->redirect_to;
        }
        if (static::$redirect_to_callback) {
            return call_user_func(static::$redirect_to_callback, $request);
        }

        return null;
    }

    /**
     * Specify the callback that should be used to generate the redirect path.
     *
     * The callback receives the current {@see Request} instance and should return
     * a string URL or null. Useful for context-dependent redirects (e.g. sending
     * API requests to a JSON 401 handler and web requests to the login page).
     *
     * @param  callable(Request): string|null  $redirect_to_callback
     * @return void
     *
     * @since 5.2
     */
    public static function redirect_using(callable $redirect_to_callback): void
    {
        static::$redirect_to_callback = $redirect_to_callback;
    }
}