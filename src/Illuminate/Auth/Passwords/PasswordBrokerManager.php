<?php

declare(strict_types=1);

namespace Illuminate\Auth\Passwords;

use Illuminate\Contracts\Auth\PasswordBrokerFactory as FactoryContract;
use InvalidArgumentException;

/**
 * @mixin \Illuminate\Contracts\Auth\PasswordBroker
 */
class PasswordBrokerManager implements FactoryContract
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
    ) {
    }

    /**
     * Attempt to get the broker from the local cache.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->brokers[$name] ?? ($this->brokers[$name] = $this->resolve($name));
    }

    /**
     * Resolve the given broker.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name): \Illuminate\Auth\Passwords\PasswordBroker
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Password resetter [{$name}] is not defined.");
        }

        // The password broker uses a token repository to validate tokens and send user
        // password e-mails, as well as validating that password reset process as an
        // aggregate service of sorts providing a convenient interface for resets.
        return new PasswordBroker(
            $this->createTokenRepository($config),
            $this->app['auth']->createUserProvider($config['provider'] ?? null),
            $this->app['events'] ?? null,
            timeboxDuration: $this->app['config']->get('auth.timebox_duration', 200000),
        );
    }

    /**
     * Create a token repository instance based on the given configuration.
     *
     * @return \Illuminate\Auth\Passwords\TokenRepositoryInterface
     */
    protected function createTokenRepository(array $config): \Illuminate\Auth\Passwords\CacheTokenRepository|\Illuminate\Auth\Passwords\DatabaseTokenRepository
    {
        $key = $this->app['config']['app.key'];

        if (str_starts_with((string) $key, 'base64:')) {
            $key = base64_decode(substr((string) $key, 7));
        }

        if (isset($config['driver']) && $config['driver'] === 'cache') {
            return new CacheTokenRepository(
                $this->app['cache']->store($config['store'] ?? null),
                $this->app['hash'],
                $key,
                ($config['expire'] ?? 60) * 60,
                $config['throttle'] ?? 0,
            );
        }

        return new DatabaseTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }

    /**
     * Get the password broker configuration.
     *
     * @param  string  $name
     * @return array|null
     */
    protected function getConfig($name)
    {
        return $this->app['config']["auth.passwords.{$name}"];
    }

    /**
     * Get the default password broker name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['auth.defaults.passwords'];
    }

    /**
     * Set the default password broker name.
     *
     * @param  string  $name
     */
    public function setDefaultDriver($name): void
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
