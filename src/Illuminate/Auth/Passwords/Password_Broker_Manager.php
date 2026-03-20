<?php

declare (strict_types=1);
namespace Illuminate\Auth\Passwords;

use Illuminate\Contracts\Auth\Password_Broker_Factory as FactoryContract;
use InvalidArgumentException;
/**
 * @mixin \Illuminate\Contracts\Auth\PasswordBroker
 */
class Password_Broker_Manager implements Factory_Contract
{
    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected $brokers = [];
    /**
     * Create a new PasswordBroker manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct(
        /**
         * The application instance.
         */
        protected $app
    )
    {
    }
    /**
     * Attempt to get the broker from the local cache.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker($name = null)
    {
        $name = $name ?: $this->get_default_driver();
        return $this->brokers[$name] ?? $this->brokers[$name] = $this->resolve($name);
    }
    /**
     * Resolve the given broker.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name): \Illuminate\Auth\Passwords\Password_Broker
    {
        $config = $this->get_config($name);
        if (is_null($config)) {
            throw new InvalidArgumentException("Password resetter [{$name}] is not defined.");
        }
        // The password broker uses a token repository to validate tokens and send user
        // password e-mails, as well as validating that password reset process as an
        // aggregate service of sorts providing a convenient interface for resets.
        return new Password_Broker($this->create_token_repository($config), $this->app['auth']->create_user_provider($config['provider'] ?? null), $this->app['events'] ?? null, timeboxDuration: $this->app['config']->get('auth.timebox_duration', 200000));
    }
    /**
     * Create a token repository instance based on the given configuration.
     *
     * @return \Illuminate\Auth\Passwords\TokenRepositoryInterface
     */
    protected function create_token_repository(array $config): \Illuminate\Auth\Passwords\Cache_Token_Repository|\Illuminate\Auth\Passwords\Database_Token_Repository
    {
        $key = $this->app['config']['app.key'];
        if (str_starts_with((string) $key, 'base64:')) {
            $key = base64_decode(substr((string) $key, 7));
        }
        if (isset($config['driver']) && $config['driver'] === 'cache') {
            return new Cache_Token_Repository($this->app['cache']->store($config['store'] ?? null), $this->app['hash'], $key, ($config['expire'] ?? 60) * 60, $config['throttle'] ?? 0);
        }
        return new Database_Token_Repository($this->app['db']->connection($config['connection'] ?? null), $this->app['hash'], $config['table'], $key, ($config['expire'] ?? 60) * 60, $config['throttle'] ?? 0);
    }
    /**
     * Get the password broker configuration.
     *
     * @param  string  $name
     * @return array|null
     */
    protected function get_config($name)
    {
        return $this->app['config']["auth.passwords.{$name}"];
    }
    /**
     * Get the default password broker name.
     *
     * @return string
     */
    public function get_default_driver()
    {
        return $this->app['config']['auth.defaults.passwords'];
    }
    /**
     * Set the default password broker name.
     *
     * @param  string  $name
     */
    public function set_default_driver($name): void
    {
        $this->app['config']['auth.defaults.passwords'] = $name;
    }
    /**
     * Dynamically call the default driver instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->broker()->{$method}(...$parameters);
    }
}