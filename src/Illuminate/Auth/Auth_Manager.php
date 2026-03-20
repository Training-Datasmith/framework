<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Closure;
use Illuminate\Contracts\Auth\Factory as FactoryContract;
use InvalidArgumentException;
/**
 * @mixin \Illuminate\Contracts\Auth\Guard
 * @mixin \Illuminate\Contracts\Auth\StatefulGuard
 */
class Auth_Manager implements Factory_Contract
{
    use Creates_User_Providers;
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;
    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $custom_creators = [];
    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected $guards = [];
    /**
     * The user resolver shared by various services.
     *
     * Determines the default user for Gate, Request, and the Authenticatable contract.
     */
    protected \Closure $user_resolver;
    /**
     * Create a new Auth manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->user_resolver = fn($guard = null) => $this->guard($guard)->user();
    }
    /**
     * Attempt to get the guard from the local cache.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard
     */
    public function guard($name = null)
    {
        $name = $name ?: $this->get_default_driver();
        return $this->guards[$name] ??= $this->resolve($name);
    }
    /**
     * Resolve the given guard.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->get_config($name);
        if (is_null($config)) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }
        if (isset($this->custom_creators[$config['driver']])) {
            return $this->call_custom_creator($name, $config);
        }
        $driver_method = 'create' . ucfirst((string) $config['driver']) . 'Driver';
        if (method_exists($this, $driver_method)) {
            return $this->{$driver_method}($name, $config);
        }
        throw new InvalidArgumentException("Auth driver [{$config['driver']}] for guard [{$name}] is not defined.");
    }
    /**
     * Call a custom driver creator.
     *
     * @param  string  $name
     * @return mixed
     */
    protected function call_custom_creator($name, array $config)
    {
        return $this->custom_creators[$config['driver']]($this->app, $name, $config);
    }
    /**
     * Create a session based authentication guard.
     *
     * @param  string  $name
     */
    public function create_session_driver($name, array $config): \Illuminate\Auth\Session_Guard
    {
        $guard = new Session_Guard($name, $this->create_user_provider($config['provider'] ?? null), $this->app['session.store'], rehashOnLogin: $this->app['config']->get('hashing.rehash_on_login', true), timeboxDuration: $this->app['config']->get('auth.timebox_duration', 200000), hashKey: $this->app['config']->get('app.key'));
        // When using the remember me functionality of the authentication services we
        // will need to be set the encryption instance of the guard, which allows
        // secure, encrypted cookie values to get generated for those cookies.
        $guard->set_cookie_jar($this->app['cookie']);
        $guard->set_dispatcher($this->app['events']);
        $guard->set_request($this->app->refresh('request', $guard, 'setRequest'));
        if (isset($config['remember'])) {
            $guard->set_remember_duration($config['remember']);
        }
        return $guard;
    }
    /**
     * Create a token based authentication guard.
     *
     * @param  string  $name
     */
    public function create_token_driver($name, array $config): \Illuminate\Auth\Token_Guard
    {
        // The token guard implements a basic API token based guard implementation
        // that takes an API token field from the request and matches it to the
        // user in the database or another persistence layer where users are.
        $guard = new Token_Guard($this->create_user_provider($config['provider'] ?? null), $this->app['request'], $config['input_key'] ?? 'api_token', $config['storage_key'] ?? 'api_token', $config['hash'] ?? false);
        $this->app->refresh('request', $guard, 'setRequest');
        return $guard;
    }
    /**
     * Get the guard configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function get_config($name)
    {
        return $this->app['config']["auth.guards.{$name}"];
    }
    /**
     * Get the default authentication driver name.
     *
     * @return string
     */
    public function get_default_driver()
    {
        return $this->app['config']['auth.defaults.guard'];
    }
    /**
     * Set the default guard driver the factory should serve.
     *
     * @param  string  $name
     */
    public function should_use($name): void
    {
        $name = $name ?: $this->get_default_driver();
        $this->set_default_driver($name);
        $this->user_resolver = fn($name = null) => $this->guard($name)->user();
    }
    /**
     * Set the default authentication driver name.
     *
     * @param  string  $name
     */
    public function set_default_driver($name): void
    {
        $this->app['config']['auth.defaults.guard'] = $name;
    }
    /**
     * Register a new callback based request guard.
     *
     * @param  string  $driver
     * @return $this
     */
    public function via_request($driver, callable $callback): static
    {
        return $this->extend($driver, function () use ($callback): \Illuminate\Auth\Request_Guard {
            $guard = new Request_Guard($callback, $this->app['request'], $this->create_user_provider());
            $this->app->refresh('request', $guard, 'setRequest');
            return $guard;
        });
    }
    /**
     * Get the user resolver callback.
     */
    public function user_resolver(): \Closure
    {
        return $this->user_resolver;
    }
    /**
     * Set the callback to be used to resolve users.
     *
     * @return $this
     */
    public function resolve_users_using(Closure $user_resolver): static
    {
        $this->user_resolver = $user_resolver;
        return $this;
    }
    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     * @return $this
     */
    public function extend($driver, Closure $callback): static
    {
        $this->custom_creators[$driver] = $callback;
        return $this;
    }
    /**
     * Register a custom provider creator Closure.
     *
     * @param  string  $name
     * @return $this
     */
    public function provider($name, Closure $callback): static
    {
        $this->custom_provider_creators[$name] = $callback;
        return $this;
    }
    /**
     * Determines if any guards have already been resolved.
     */
    public function has_resolved_guards(): bool
    {
        return count($this->guards) > 0;
    }
    /**
     * Forget all of the resolved guard instances.
     *
     * @return $this
     */
    public function forget_guards(): static
    {
        $this->guards = [];
        return $this;
    }
    /**
     * Set the application instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return $this
     */
    public function set_application($app): static
    {
        $this->app = $app;
        return $this;
    }
    /**
     * Dynamically call the default driver instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->guard()->{$method}(...$parameters);
    }
}