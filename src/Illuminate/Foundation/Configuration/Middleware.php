<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Configuration;

use Closure;
use Illuminate\Auth\Authentication_Exception;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Redirect_If_Authenticated;
use Illuminate\Cookie\Middleware\Encrypt_Cookies;
use Illuminate\Foundation\Http\Middleware\Convert_Empty_Strings_To_Null;
use Illuminate\Foundation\Http\Middleware\Prevent_Requests_During_Maintenance;
use Illuminate\Foundation\Http\Middleware\Trim_Strings;
use Illuminate\Foundation\Http\Middleware\Validate_Csrf_Token;
use Illuminate\Http\Middleware\Trust_Hosts;
use Illuminate\Http\Middleware\Trust_Proxies;
use Illuminate\Routing\Middleware\Validate_Signature;
use Illuminate\Session\Middleware\Authenticate_Session;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
class Middleware
{
    /**
     * The user defined global middleware stack.
     *
     * @var array
     */
    protected $global = [];
    /**
     * The middleware that should be prepended to the global middleware stack.
     *
     * @var array
     */
    protected $prepends = [];
    /**
     * The middleware that should be appended to the global middleware stack.
     *
     * @var array
     */
    protected $appends = [];
    /**
     * The middleware that should be removed from the global middleware stack.
     *
     * @var array
     */
    protected $removals = [];
    /**
     * The middleware that should be replaced in the global middleware stack.
     *
     * @var array
     */
    protected $replacements = [];
    /**
     * The user defined middleware groups.
     *
     * @var array
     */
    protected $groups = [];
    /**
     * The middleware that should be prepended to the specified groups.
     *
     * @var array
     */
    protected $group_prepends = [];
    /**
     * The middleware that should be appended to the specified groups.
     *
     * @var array
     */
    protected $group_appends = [];
    /**
     * The middleware that should be removed from the specified groups.
     *
     * @var array
     */
    protected $group_removals = [];
    /**
     * The middleware that should be replaced in the specified groups.
     *
     * @var array
     */
    protected $group_replacements = [];
    /**
     * The Folio / page middleware for the application.
     *
     * @var array
     */
    protected $page_middleware = [];
    /**
     * Indicates if the "trust hosts" middleware is enabled.
     *
     * @var bool
     */
    protected $trust_hosts = false;
    /**
     * Indicates if Sanctum's frontend state middleware is enabled.
     *
     * @var bool
     */
    protected $stateful_api = false;
    /**
     * Indicates the API middleware group's rate limiter.
     *
     * @var string
     */
    protected $api_limiter;
    /**
     * Indicates if Redis throttling should be applied.
     *
     * @var bool
     */
    protected $throttle_with_redis = false;
    /**
     * Indicates if sessions should be authenticated for the "web" middleware group.
     *
     * @var bool
     */
    protected $authenticated_sessions = false;
    /**
     * The custom middleware aliases.
     *
     * @var array
     */
    protected $custom_aliases = [];
    /**
     * The custom middleware priority definition.
     *
     * @var array
     */
    protected $priority = [];
    /**
     * The middleware to prepend to the middleware priority definition.
     *
     * @var array
     */
    protected $prepend_priority = [];
    /**
     * The middleware to append to the middleware priority definition.
     *
     * @var array
     */
    protected $append_priority = [];
    /**
     * Prepend middleware to the application's global middleware stack.
     *
     * @return $this
     */
    public function prepend(array|string $middleware): static
    {
        $this->prepends = array_merge(Arr::wrap($middleware), $this->prepends);
        return $this;
    }
    /**
     * Append middleware to the application's global middleware stack.
     *
     * @return $this
     */
    public function append(array|string $middleware): static
    {
        $this->appends = array_merge($this->appends, Arr::wrap($middleware));
        return $this;
    }
    /**
     * Remove middleware from the application's global middleware stack.
     *
     * @return $this
     */
    public function remove(array|string $middleware): static
    {
        $this->removals = array_merge($this->removals, Arr::wrap($middleware));
        return $this;
    }
    /**
     * Specify a middleware that should be replaced with another middleware.
     *
     * @return $this
     */
    public function replace(string $search, string $replace): static
    {
        $this->replacements[$search] = $replace;
        return $this;
    }
    /**
     * Define the global middleware for the application.
     *
     * @return $this
     */
    public function use(array $middleware): static
    {
        $this->global = $middleware;
        return $this;
    }
    /**
     * Define a middleware group.
     *
     * @return $this
     */
    public function group(string $group, array $middleware): static
    {
        $this->groups[$group] = $middleware;
        return $this;
    }
    /**
     * Prepend the given middleware to the specified group.
     *
     * @return $this
     */
    public function prepend_to_group(string $group, array|string $middleware): static
    {
        $this->group_prepends[$group] = array_merge(Arr::wrap($middleware), $this->group_prepends[$group] ?? []);
        return $this;
    }
    /**
     * Append the given middleware to the specified group.
     *
     * @return $this
     */
    public function append_to_group(string $group, array|string $middleware): static
    {
        $this->group_appends[$group] = array_merge($this->group_appends[$group] ?? [], Arr::wrap($middleware));
        return $this;
    }
    /**
     * Remove the given middleware from the specified group.
     *
     * @return $this
     */
    public function remove_from_group(string $group, array|string $middleware): static
    {
        $this->group_removals[$group] = array_merge(Arr::wrap($middleware), $this->group_removals[$group] ?? []);
        return $this;
    }
    /**
     * Replace the given middleware in the specified group with another middleware.
     *
     * @return $this
     */
    public function replace_in_group(string $group, string $search, string $replace): static
    {
        $this->group_replacements[$group][$search] = $replace;
        return $this;
    }
    /**
     * Modify the middleware in the "web" group.
     *
     * @return $this
     */
    public function web(array|string $append = [], array|string $prepend = [], array|string $remove = [], array $replace = []): static
    {
        return $this->modify_group('web', $append, $prepend, $remove, $replace);
    }
    /**
     * Modify the middleware in the "api" group.
     *
     * @return $this
     */
    public function api(array|string $append = [], array|string $prepend = [], array|string $remove = [], array $replace = []): static
    {
        return $this->modify_group('api', $append, $prepend, $remove, $replace);
    }
    /**
     * Modify the middleware in the given group.
     *
     * @return $this
     */
    protected function modify_group(string $group, array|string $append, array|string $prepend, array|string $remove, array $replace): static
    {
        if (!empty($append)) {
            $this->append_to_group($group, $append);
        }
        if (!empty($prepend)) {
            $this->prepend_to_group($group, $prepend);
        }
        if (!empty($remove)) {
            $this->remove_from_group($group, $remove);
        }
        foreach ($replace as $search => $replace) {
            $this->replace_in_group($group, $search, $replace);
        }
        return $this;
    }
    /**
     * Register the Folio / page middleware for the application.
     *
     * @return $this
     */
    public function pages(array $middleware): static
    {
        $this->page_middleware = $middleware;
        return $this;
    }
    /**
     * Register additional middleware aliases.
     *
     * @return $this
     */
    public function alias(array $aliases): static
    {
        $this->custom_aliases = $aliases;
        return $this;
    }
    /**
     * Define the middleware priority for the application.
     *
     * @return $this
     */
    public function priority(array $priority): static
    {
        $this->priority = $priority;
        return $this;
    }
    /**
     * Prepend middleware to the priority middleware.
     *
     * @param  array|string  $before
     * @param  string  $prepend
     * @return $this
     */
    public function prepend_to_priority_list($before, $prepend): static
    {
        $this->prepend_priority[$prepend] = $before;
        return $this;
    }
    /**
     * Append middleware to the priority middleware.
     *
     * @param  array|string  $after
     * @param  string  $append
     * @return $this
     */
    public function append_to_priority_list($after, $append): static
    {
        $this->append_priority[$append] = $after;
        return $this;
    }
    /**
     * Get the global middleware.
     */
    public function get_global_middleware(): array
    {
        $middleware = $this->global ?: array_values(array_filter([\Illuminate\Http\Middleware\Validate_Path_Encoding::class, \Illuminate\Foundation\Http\Middleware\Invoke_Deferred_Callbacks::class, $this->trust_hosts ? \Illuminate\Http\Middleware\Trust_Hosts::class : null, \Illuminate\Http\Middleware\Trust_Proxies::class, \Illuminate\Http\Middleware\Handle_Cors::class, \Illuminate\Foundation\Http\Middleware\Prevent_Requests_During_Maintenance::class, \Illuminate\Http\Middleware\Validate_Post_Size::class, \Illuminate\Foundation\Http\Middleware\Trim_Strings::class, \Illuminate\Foundation\Http\Middleware\Convert_Empty_Strings_To_Null::class]));
        $middleware = array_map(fn($middleware) => $this->replacements[$middleware] ?? $middleware, $middleware);
        return array_values(array_filter(array_diff(array_unique(array_merge($this->prepends, $middleware, $this->appends)), $this->removals)));
    }
    /**
     * Get the middleware groups.
     */
    public function get_middleware_groups(): array
    {
        $middleware = ['web' => array_values(array_filter([\Illuminate\Cookie\Middleware\Encrypt_Cookies::class, \Illuminate\Cookie\Middleware\Add_Queued_Cookies_To_Response::class, \Illuminate\Session\Middleware\Start_Session::class, \Illuminate\View\Middleware\Share_Errors_From_Session::class, \Illuminate\Foundation\Http\Middleware\Validate_Csrf_Token::class, \Illuminate\Routing\Middleware\Substitute_Bindings::class, $this->authenticated_sessions ? 'auth.session' : null])), 'api' => array_values(array_filter([$this->stateful_api ? \Laravel\Sanctum\Http\Middleware\Ensure_Frontend_Requests_Are_Stateful::class : null, $this->api_limiter ? 'throttle:' . $this->api_limiter : null, \Illuminate\Routing\Middleware\Substitute_Bindings::class]))];
        $middleware = array_merge($middleware, $this->groups);
        foreach ($middleware as $group => $grouped_middleware) {
            foreach ($grouped_middleware as $index => $group_middleware) {
                if (isset($this->group_replacements[$group][$group_middleware])) {
                    $middleware[$group][$index] = $this->group_replacements[$group][$group_middleware];
                }
            }
        }
        foreach ($this->group_removals as $group => $removals) {
            $middleware[$group] = array_values(array_filter(array_diff($middleware[$group] ?? [], $removals)));
        }
        foreach ($this->group_prepends as $group => $prepends) {
            $middleware[$group] = array_values(array_filter(array_unique(array_merge($prepends, $middleware[$group] ?? []))));
        }
        foreach ($this->group_appends as $group => $appends) {
            $middleware[$group] = array_values(array_filter(array_unique(array_merge($middleware[$group] ?? [], $appends))));
        }
        return $middleware;
    }
    /**
     * Configure where guests are redirected by the "auth" middleware.
     *
     * @return $this
     */
    public function redirect_guests_to(callable|string $redirect): static
    {
        return $this->redirect_to(guests: $redirect);
    }
    /**
     * Configure where users are redirected by the "guest" middleware.
     *
     * @return $this
     */
    public function redirect_users_to(callable|string $redirect): static
    {
        return $this->redirect_to(users: $redirect);
    }
    /**
     * Configure where users are redirected by the authentication and guest middleware.
     *
     * @return $this
     */
    public function redirect_to(callable|string|null $guests = null, callable|string|null $users = null): static
    {
        $guests = is_string($guests) ? fn(): string => $guests : $guests;
        $users = is_string($users) ? fn(): string => $users : $users;
        if ($guests) {
            Authenticate::redirect_using($guests);
            Authenticate_Session::redirect_using($guests);
            Authentication_Exception::redirect_using($guests);
        }
        if ($users) {
            Redirect_If_Authenticated::redirect_using($users);
        }
        return $this;
    }
    /**
     * Configure the cookie encryption middleware.
     *
     * @param  array<int, string>  $except
     * @return $this
     */
    public function encrypt_cookies(array $except = []): static
    {
        Encrypt_Cookies::except($except);
        return $this;
    }
    /**
     * Configure the CSRF token validation middleware.
     *
     * @return $this
     */
    public function validate_csrf_tokens(array $except = []): static
    {
        Validate_Csrf_Token::except($except);
        return $this;
    }
    /**
     * Configure the URL signature validation middleware.
     *
     * @return $this
     */
    public function validate_signatures(array $except = []): static
    {
        Validate_Signature::except($except);
        return $this;
    }
    /**
     * Configure the empty string conversion middleware.
     *
     * @param  array<int, (\Closure(\Illuminate\Http\Request): bool)>  $except
     * @return $this
     */
    public function convert_empty_strings_to_null(array $except = []): static
    {
        (new Collection($except))->each(fn(Closure $callback) => Convert_Empty_Strings_To_Null::skip_when($callback));
        return $this;
    }
    /**
     * Configure the string trimming middleware.
     *
     * @param  array<int, (\Closure(\Illuminate\Http\Request): bool)|string>  $except
     * @return $this
     */
    public function trim_strings(array $except = []): static
    {
        [$skip_when, $except] = (new Collection($except))->partition(fn($value): bool => $value instanceof Closure);
        $skip_when->each(fn(Closure $callback) => Trim_Strings::skip_when($callback));
        Trim_Strings::except($except->all());
        return $this;
    }
    /**
     * Indicate that the trusted host middleware should be enabled.
     *
     * @param  array<int, string>|(callable(): array<int, string>)|null  $at
     * @return $this
     */
    public function trust_hosts(array|callable|null $at = null, bool $subdomains = true): static
    {
        $this->trust_hosts = true;
        if (!is_null($at)) {
            Trust_Hosts::at($at, $subdomains);
        }
        return $this;
    }
    /**
     * Configure the trusted proxies for the application.
     *
     * @param  array<int, string>|string|null  $at
     * @return $this
     */
    public function trust_proxies(array|string|null $at = null, ?int $headers = null): static
    {
        if (!is_null($at)) {
            Trust_Proxies::at($at);
        }
        if (!is_null($headers)) {
            Trust_Proxies::with_headers($headers);
        }
        return $this;
    }
    /**
     * Configure the middleware that prevents requests during maintenance mode.
     *
     * @param  array<int, string>  $except
     * @return $this
     */
    public function prevent_requests_during_maintenance(array $except = []): static
    {
        Prevent_Requests_During_Maintenance::except($except);
        return $this;
    }
    /**
     * Indicate that Sanctum's frontend state middleware should be enabled.
     *
     * @return $this
     */
    public function stateful_api(): static
    {
        $this->stateful_api = true;
        return $this;
    }
    /**
     * Indicate that the API middleware group's throttling middleware should be enabled.
     *
     * @param  string  $limiter
     * @param  bool  $redis
     * @return $this
     */
    public function throttle_api($limiter = 'api', $redis = false): static
    {
        $this->api_limiter = $limiter;
        if ($redis) {
            $this->throttle_with_redis();
        }
        return $this;
    }
    /**
     * Indicate that Laravel's throttling middleware should use Redis.
     *
     * @return $this
     */
    public function throttle_with_redis(): static
    {
        $this->throttle_with_redis = true;
        return $this;
    }
    /**
     * Indicate that sessions should be authenticated for the "web" middleware group.
     *
     * @return $this
     */
    public function authenticate_sessions(): static
    {
        $this->authenticated_sessions = true;
        return $this;
    }
    /**
     * Get the Folio / page middleware for the application.
     *
     * @return array
     */
    public function get_page_middleware()
    {
        return $this->page_middleware;
    }
    /**
     * Get the middleware aliases.
     */
    public function get_middleware_aliases(): array
    {
        return array_merge($this->default_aliases(), $this->custom_aliases);
    }
    /**
     * Get the default middleware aliases.
     */
    protected function default_aliases(): array
    {
        $aliases = ['auth' => \Illuminate\Auth\Middleware\Authenticate::class, 'auth.basic' => \Illuminate\Auth\Middleware\Authenticate_With_Basic_Auth::class, 'auth.session' => \Illuminate\Session\Middleware\Authenticate_Session::class, 'cache.headers' => \Illuminate\Http\Middleware\Set_Cache_Headers::class, 'can' => \Illuminate\Auth\Middleware\Authorize::class, 'guest' => \Illuminate\Auth\Middleware\Redirect_If_Authenticated::class, 'password.confirm' => \Illuminate\Auth\Middleware\Require_Password::class, 'precognitive' => \Illuminate\Foundation\Http\Middleware\Handle_Precognitive_Requests::class, 'signed' => \Illuminate\Routing\Middleware\Validate_Signature::class, 'throttle' => $this->throttle_with_redis ? \Illuminate\Routing\Middleware\Throttle_Requests_With_Redis::class : \Illuminate\Routing\Middleware\Throttle_Requests::class, 'verified' => \Illuminate\Auth\Middleware\Ensure_Email_Is_Verified::class];
        if (class_exists(\Spark\Http\Middleware\Verify_Billable_Is_Subscribed::class)) {
            $aliases['subscribed'] = \Spark\Http\Middleware\Verify_Billable_Is_Subscribed::class;
        }
        return $aliases;
    }
    /**
     * Get the middleware priority for the application.
     *
     * @return array
     */
    public function get_middleware_priority()
    {
        return $this->priority;
    }
    /**
     * Get the middleware to prepend to the middleware priority definition.
     *
     * @return array
     */
    public function get_middleware_priority_prepends()
    {
        return $this->prepend_priority;
    }
    /**
     * Get the middleware to append to the middleware priority definition.
     *
     * @return array
     */
    public function get_middleware_priority_appends()
    {
        return $this->append_priority;
    }
}