<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Current_Device_Logout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Other_Device_Logout;
use Illuminate\Auth\Events\Validated;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Stateful_Guard;
use Illuminate\Contracts\Auth\Supports_Basic_Auth;
use Illuminate\Contracts\Auth\User_Provider;
use Illuminate\Contracts\Cookie\Queueing_Factory as CookieJar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Exception\Unauthorized_Http_Exception;
class Session_Guard implements Stateful_Guard, Supports_Basic_Auth
{
    use Guard_Helpers;
    use Macroable;
    /**
     * The user we last attempted to retrieve.
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable
     */
    protected $last_attempted;
    /**
     * Indicates if the user was authenticated via a recaller cookie.
     *
     * @var bool
     */
    protected $via_remember = false;
    /**
     * The number of minutes that the "remember me" cookie should be valid for.
     *
     * @var int
     */
    protected $remember_duration = 576000;
    /**
     * The Illuminate cookie creator service.
     *
     * @var \Illuminate\Contracts\Cookie\QueueingFactory
     */
    protected $cookie;
    /**
     * The request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;
    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;
    /**
     * The timebox instance.
     */
    protected \Illuminate\Support\Timebox $timebox;
    /**
     * Indicates if the logout method has been called.
     *
     * @var bool
     */
    protected $logged_out = false;
    /**
     * Indicates if a token user retrieval has been attempted.
     *
     * @var bool
     */
    protected $recall_attempted = false;
    /**
     * Create a new authentication guard.
     */
    public function __construct(
        /**
         * The name of the guard. Typically "web".
         *
         * Corresponds to guard name in authentication configuration.
         */
        public readonly string $name,
        User_Provider $provider,
        /**
         * The session used by the guard.
         */
        protected \Illuminate\Contracts\Session\Session $session,
        ?Request $request = null,
        ?Timebox $timebox = null,
        /**
         * Indicates if passwords should be rehashed on login if needed.
         */
        protected bool $rehash_on_login = true,
        /**
         * The number of microseconds that the timebox should wait for.
         */
        protected int $timebox_duration = 200000,
        /**
         * The key used to hash recaller cookie values.
         */
        protected ?string $hash_key = null
    )
    {
        $this->request = $request;
        $this->provider = $provider;
        $this->timebox = $timebox ?: new Timebox();
    }
    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        if ($this->logged_out) {
            return;
        }
        // If we've already retrieved the user for the current request we can just
        // return it back immediately. We do not want to fetch the user data on
        // every call to this method because that would be tremendously slow.
        if (!is_null($this->user)) {
            return $this->user;
        }
        $id = $this->session->get($this->get_name());
        // First we will try to load the user using the identifier in the session if
        // one exists. Otherwise we will check for a "remember me" cookie in this
        // request, and if one exists, attempt to retrieve the user using that.
        if (!is_null($id) && $this->user = $this->provider->retrieve_by_id($id)) {
            $this->fire_authenticated_event($this->user);
        }
        // If the user is null, but we decrypt a "recaller" cookie we can attempt to
        // pull the user data on that cookie which serves as a remember cookie on
        // the application. Once we have a user we can return it to the caller.
        if (is_null($this->user) && !is_null($recaller = $this->recaller())) {
            $this->user = $this->user_from_recaller($recaller);
            if ($this->user) {
                $this->update_session($this->user->get_auth_identifier());
                $this->fire_login_event($this->user, true);
            }
        }
        return $this->user;
    }
    /**
     * Pull a user from the repository by its "remember me" cookie token.
     *
     * @param  \Illuminate\Auth\Recaller  $recaller
     * @return mixed
     */
    protected function user_from_recaller($recaller)
    {
        if (!$recaller->valid() || $this->recall_attempted) {
            return;
        }
        // If the user is null, but we decrypt a "recaller" cookie we can attempt to
        // pull the user data on that cookie which serves as a remember cookie on
        // the application. Once we have a user we can return it to the caller.
        $this->recall_attempted = true;
        $this->via_remember = !is_null($user = $this->provider->retrieve_by_token($recaller->id(), $recaller->token()));
        return $user;
    }
    /**
     * Get the decrypted recaller cookie for the request.
     *
     * @return \Illuminate\Auth\Recaller|null
     */
    protected function recaller()
    {
        if (is_null($this->request)) {
            return;
        }
        if ($recaller = $this->request->cookies->get($this->get_recaller_name())) {
            return new Recaller($recaller);
        }
    }
    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|string|null
     */
    public function id()
    {
        if ($this->logged_out) {
            return;
        }
        return $this->user() ? $this->user()->get_auth_identifier() : $this->session->get($this->get_name());
    }
    /**
     * Log a user into the application without sessions or cookies.
     */
    public function once(array $credentials = []): bool
    {
        $this->fire_attempt_event($credentials);
        if ($this->validate($credentials)) {
            $this->rehash_password_if_required($this->last_attempted, $credentials);
            $this->set_user($this->last_attempted);
            return true;
        }
        $this->fire_failed_event($this->last_attempted, $credentials);
        return false;
    }
    /**
     * Log the given user ID into the application without sessions or cookies.
     *
     * @param  mixed  $id
     * @return \Illuminate\Contracts\Auth\Authenticatable|false
     */
    public function once_using_id($id)
    {
        if (!is_null($user = $this->provider->retrieve_by_id($id))) {
            $this->set_user($user);
            return $user;
        }
        return false;
    }
    /**
     * Validate a user's credentials.
     *
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        return $this->timebox->call(function ($timebox) use ($credentials) {
            $this->last_attempted = $user = $this->provider->retrieve_by_credentials($credentials);
            $validated = $this->has_valid_credentials($user, $credentials);
            if ($validated) {
                $timebox->return_early();
            }
            return $validated;
        }, $this->timebox_duration);
    }
    /**
     * Attempt to authenticate using HTTP Basic Auth.
     *
     * @param  string  $field
     * @param  array  $extraConditions
     * @return \Symfony\Component\HttpFoundation\Response|null
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     */
    public function basic($field = 'email', $extra_conditions = [])
    {
        if ($this->check()) {
            return;
        }
        // If a username is set on the HTTP basic request, we will return out without
        // interrupting the request lifecycle. Otherwise, we'll need to generate a
        // request indicating that the given credentials were invalid for login.
        if ($this->attempt_basic($this->get_request(), $field, $extra_conditions)) {
            return;
        }
        return $this->failed_basic_response();
    }
    /**
     * Perform a stateless HTTP Basic login attempt.
     *
     * @param  string  $field
     * @param  array  $extraConditions
     * @return \Symfony\Component\HttpFoundation\Response|null
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     */
    public function once_basic($field = 'email', $extra_conditions = [])
    {
        $credentials = $this->basic_credentials($this->get_request(), $field);
        if (!$this->once(array_merge($credentials, $extra_conditions))) {
            return $this->failed_basic_response();
        }
    }
    /**
     * Attempt to authenticate using basic authentication.
     *
     * @param  string  $field
     * @param  array  $extraConditions
     * @return bool
     */
    protected function attempt_basic(Request $request, $field, $extra_conditions = [])
    {
        if (!$request->get_user()) {
            return false;
        }
        return $this->attempt(array_merge($this->basic_credentials($request, $field), $extra_conditions));
    }
    /**
     * Get the credential array for an HTTP Basic request.
     *
     * @param  string  $field
     */
    protected function basic_credentials(Request $request, $field): array
    {
        return [$field => $request->get_user(), 'password' => $request->get_password()];
    }
    /**
     * Get the response for basic authentication.
     *
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     */
    protected function failed_basic_response(): never
    {
        throw new Unauthorized_Http_Exception('Basic', 'Invalid credentials.');
    }
    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param  bool  $remember
     * @return bool
     */
    public function attempt(array $credentials = [], $remember = false)
    {
        return $this->timebox->call(function ($timebox) use ($credentials, $remember): bool {
            $this->fire_attempt_event($credentials, $remember);
            $this->last_attempted = $user = $this->provider->retrieve_by_credentials($credentials);
            // If an implementation of UserInterface was returned, we'll ask the provider
            // to validate the user against the given credentials, and if they are in
            // fact valid we'll log the users into the application and return true.
            if ($this->has_valid_credentials($user, $credentials)) {
                $this->rehash_password_if_required($user, $credentials);
                $this->login($user, $remember);
                $timebox->return_early();
                return true;
            }
            // If the authentication attempt fails we will fire an event so that the user
            // may be notified of any suspicious attempts to access their account from
            // an unrecognized user. A developer may listen to this event as needed.
            $this->fire_failed_event($user, $credentials);
            return false;
        }, $this->timebox_duration);
    }
    /**
     * Attempt to authenticate a user with credentials and additional callbacks.
     *
     * @param  array|callable|null  $callbacks
     * @param  bool  $remember
     * @return bool
     */
    public function attempt_when(array $credentials = [], $callbacks = null, $remember = false)
    {
        return $this->timebox->call(function ($timebox) use ($credentials, $callbacks, $remember): bool {
            $this->fire_attempt_event($credentials, $remember);
            $this->last_attempted = $user = $this->provider->retrieve_by_credentials($credentials);
            // This method does the exact same thing as attempt, but also executes callbacks after
            // the user is retrieved and validated. If one of the callbacks returns falsy we do
            // not login the user. Instead, we will fail the specific authentication attempt.
            if ($this->has_valid_credentials($user, $credentials) && $this->should_login($callbacks, $user)) {
                $this->rehash_password_if_required($user, $credentials);
                $this->login($user, $remember);
                $timebox->return_early();
                return true;
            }
            $this->fire_failed_event($user, $credentials);
            return false;
        }, $this->timebox_duration);
    }
    /**
     * Determine if the user matches the credentials.
     *
     * @param  mixed  $user
     * @param  array  $credentials
     * @return bool
     */
    protected function has_valid_credentials($user, $credentials)
    {
        $validated = !is_null($user) && $this->provider->validate_credentials($user, $credentials);
        if ($validated) {
            $this->fire_validated_event($user);
        }
        return $validated;
    }
    /**
     * Determine if the user should login by executing the given callbacks.
     *
     * @param  array|callable|null  $callbacks
     */
    protected function should_login($callbacks, Authenticatable_Contract $user): bool
    {
        foreach (Arr::wrap($callbacks) as $callback) {
            if (!$callback($user, $this)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Rehash the user's password if enabled and required.
     *
     * @return void
     */
    protected function rehash_password_if_required(
        Authenticatable_Contract $user,
        #[\Sensitive_Parameter]
        array $credentials
    )
    {
        if ($this->rehash_on_login) {
            $this->provider->rehash_password_if_required($user, $credentials);
        }
    }
    /**
     * Log the given user ID into the application.
     *
     * @param  mixed  $id
     * @param  bool  $remember
     * @return \Illuminate\Contracts\Auth\Authenticatable|false
     */
    public function login_using_id($id, $remember = false)
    {
        if (!is_null($user = $this->provider->retrieve_by_id($id))) {
            $this->login($user, $remember);
            return $user;
        }
        return false;
    }
    /**
     * Log a user into the application.
     *
     * @param  bool  $remember
     */
    public function login(Authenticatable_Contract $user, $remember = false): void
    {
        $this->update_session($user->get_auth_identifier());
        // If the user should be permanently "remembered" by the application we will
        // queue a permanent cookie that contains the encrypted copy of the user
        // identifier. We will then decrypt this later to retrieve the users.
        if ($remember) {
            $this->ensure_remember_token_is_set($user);
            $this->queue_recaller_cookie($user);
        }
        // If we have an event dispatcher instance set we will fire an event so that
        // any listeners will hook into the authentication events and run actions
        // based on the login and logout events fired from the guard instances.
        $this->fire_login_event($user, $remember);
        $this->set_user($user);
    }
    /**
     * Update the session with the given ID and regenerate the session's token.
     *
     * @param  string  $id
     * @return void
     */
    protected function update_session($id)
    {
        $this->session->put($this->get_name(), $id);
        $this->session->regenerate(true);
    }
    /**
     * Create a new "remember me" token for the user if one doesn't already exist.
     *
     * @return void
     */
    protected function ensure_remember_token_is_set(Authenticatable_Contract $user)
    {
        if (empty($user->get_remember_token())) {
            $this->cycle_remember_token($user);
        }
    }
    /**
     * Queue the recaller cookie into the cookie jar.
     *
     * @return void
     */
    protected function queue_recaller_cookie(Authenticatable_Contract $user)
    {
        $this->get_cookie_jar()->queue($this->create_recaller($user->get_auth_identifier() . '|' . $user->get_remember_token() . '|' . $this->hash_password_for_cookie($user->get_auth_password())));
    }
    /**
     * Create a "remember me" cookie for a given ID.
     *
     * @param  string  $value
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function create_recaller($value)
    {
        return $this->get_cookie_jar()->make($this->get_recaller_name(), $value, $this->get_remember_duration());
    }
    /**
     * Create a HMAC of the password hash for storage in cookies.
     *
     * @param  string  $passwordHash
     */
    public function hash_password_for_cookie($password_hash): string
    {
        return hash_hmac('sha256', $password_hash, $this->hash_key ?? 'base-key-for-password-hash-mac');
    }
    /**
     * Log the user out of the application.
     */
    public function logout(): void
    {
        $user = $this->user();
        $this->clear_user_data_from_storage();
        if (!is_null($this->user) && !empty($user->get_remember_token())) {
            $this->cycle_remember_token($user);
        }
        // If we have an event dispatcher instance, we can fire off the logout event
        // so any further processing can be done. This allows the developer to be
        // listening for anytime a user signs out of this application manually.
        $this->events?->dispatch(new Logout($this->name, $user));
        // Once we have fired the logout event we will clear the users out of memory
        // so they are no longer available as the user is no longer considered as
        // being signed into this application and should not be available here.
        $this->user = null;
        $this->logged_out = true;
    }
    /**
     * Log the user out of the application on their current device only.
     *
     * This method does not cycle the "remember" token.
     */
    public function logout_current_device(): void
    {
        $user = $this->user();
        $this->clear_user_data_from_storage();
        // If we have an event dispatcher instance, we can fire off the logout event
        // so any further processing can be done. This allows the developer to be
        // listening for anytime a user signs out of this application manually.
        $this->events?->dispatch(new Current_Device_Logout($this->name, $user));
        // Once we have fired the logout event we will clear the users out of memory
        // so they are no longer available as the user is no longer considered as
        // being signed into this application and should not be available here.
        $this->user = null;
        $this->logged_out = true;
    }
    /**
     * Remove the user data from the session and cookies.
     *
     * @return void
     */
    protected function clear_user_data_from_storage()
    {
        $this->session->remove($this->get_name());
        $this->get_cookie_jar()->unqueue($this->get_recaller_name());
        if (!is_null($this->recaller())) {
            $this->get_cookie_jar()->queue($this->get_cookie_jar()->forget($this->get_recaller_name()));
        }
    }
    /**
     * Refresh the "remember me" token for the user.
     *
     * @return void
     */
    protected function cycle_remember_token(Authenticatable_Contract $user)
    {
        $user->set_remember_token($token = Str::random(60));
        $this->provider->update_remember_token($user, $token);
    }
    /**
     * Invalidate other sessions for the current user.
     *
     * The application must be using the AuthenticateSession middleware.
     *
     * @param  string  $password
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function logout_other_devices($password)
    {
        if (!$this->user()) {
            return;
        }
        $result = $this->rehash_user_password_for_device_logout($password);
        if ($this->recaller() || $this->get_cookie_jar()->has_queued($this->get_recaller_name())) {
            $this->queue_recaller_cookie($this->user());
        }
        $this->fire_other_device_logout_event($this->user());
        return $result;
    }
    /**
     * Rehash the current user's password for logging out other devices via AuthenticateSession.
     *
     * @param  string  $password
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     *
     * @throws \InvalidArgumentException
     */
    protected function rehash_user_password_for_device_logout($password)
    {
        $user = $this->user();
        if (!Hash::check($password, $user->get_auth_password())) {
            throw new InvalidArgumentException('The given password does not match the current password.');
        }
        $this->provider->rehash_password_if_required($user, ['password' => $password], force: true);
    }
    /**
     * Register an authentication attempt event listener.
     *
     * @param  mixed  $callback
     */
    public function attempting($callback): void
    {
        $this->events?->listen(Events\Attempting::class, $callback);
    }
    /**
     * Fire the attempt event with the arguments.
     *
     * @param  bool  $remember
     * @return void
     */
    protected function fire_attempt_event(array $credentials, $remember = false)
    {
        $this->events?->dispatch(new Attempting($this->name, $credentials, $remember));
    }
    /**
     * Fires the validated event if the dispatcher is set.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return void
     */
    protected function fire_validated_event($user)
    {
        $this->events?->dispatch(new Validated($this->name, $user));
    }
    /**
     * Fire the login event if the dispatcher is set.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  bool  $remember
     * @return void
     */
    protected function fire_login_event($user, $remember = false)
    {
        $this->events?->dispatch(new Login($this->name, $user, $remember));
    }
    /**
     * Fire the authenticated event if the dispatcher is set.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return void
     */
    protected function fire_authenticated_event($user)
    {
        $this->events?->dispatch(new Authenticated($this->name, $user));
    }
    /**
     * Fire the other device logout event if the dispatcher is set.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return void
     */
    protected function fire_other_device_logout_event($user)
    {
        $this->events?->dispatch(new Other_Device_Logout($this->name, $user));
    }
    /**
     * Fire the failed authentication attempt event with the given arguments.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @return void
     */
    protected function fire_failed_event($user, array $credentials)
    {
        $this->events?->dispatch(new Failed($this->name, $user, $credentials));
    }
    /**
     * Get the last user we attempted to authenticate.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    public function get_last_attempted()
    {
        return $this->last_attempted;
    }
    /**
     * Get a unique identifier for the auth session value.
     */
    public function get_name(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }
    /**
     * Get the name of the cookie used to store the "recaller".
     */
    public function get_recaller_name(): string
    {
        return 'remember_' . $this->name . '_' . sha1(static::class);
    }
    /**
     * Determine if the user was authenticated via "remember me" cookie.
     *
     * @return bool
     */
    public function via_remember()
    {
        return $this->via_remember;
    }
    /**
     * Get the number of minutes the remember me cookie should be valid for.
     *
     * @return int
     */
    protected function get_remember_duration()
    {
        return $this->remember_duration;
    }
    /**
     * Set the number of minutes the remember me cookie should be valid for.
     *
     * @param  int  $minutes
     * @return $this
     */
    public function set_remember_duration($minutes): static
    {
        $this->remember_duration = $minutes;
        return $this;
    }
    /**
     * Get the cookie creator instance used by the guard.
     *
     * @return \Illuminate\Contracts\Cookie\QueueingFactory
     *
     * @throws \RuntimeException
     */
    public function get_cookie_jar()
    {
        if (!isset($this->cookie)) {
            throw new RuntimeException('Cookie jar has not been set.');
        }
        return $this->cookie;
    }
    /**
     * Set the cookie creator instance used by the guard.
     */
    public function set_cookie_jar(Cookie_Jar $cookie): void
    {
        $this->cookie = $cookie;
    }
    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher
     */
    public function get_dispatcher()
    {
        return $this->events;
    }
    /**
     * Set the event dispatcher instance.
     */
    public function set_dispatcher(Dispatcher $events): void
    {
        $this->events = $events;
    }
    /**
     * Get the session store used by the guard.
     */
    public function get_session(): \Illuminate\Contracts\Session\Session
    {
        return $this->session;
    }
    /**
     * Return the currently cached user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function get_user()
    {
        return $this->user;
    }
    /**
     * Set the current user.
     *
     * @return $this
     */
    public function set_user(Authenticatable_Contract $user): static
    {
        $this->user = $user;
        $this->logged_out = false;
        $this->fire_authenticated_event($user);
        return $this;
    }
    /**
     * Get the current request instance.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function get_request()
    {
        return $this->request ?: Request::create_from_globals();
    }
    /**
     * Set the current request instance.
     *
     * @return $this
     */
    public function set_request(Request $request): static
    {
        $this->request = $request;
        return $this;
    }
    /**
     * Get the timebox instance used by the guard.
     */
    public function get_timebox(): \Illuminate\Support\Timebox
    {
        return $this->timebox;
    }
}