<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use InvalidArgumentException;
trait Creates_User_Providers
{
    /**
     * The registered custom provider creators.
     *
     * @var array
     */
    protected $custom_provider_creators = [];
    /**
     * Create the user provider implementation for the driver.
     *
     * @param  string|null  $provider
     * @return \Illuminate\Contracts\Auth\UserProvider|null
     *
     * @throws \InvalidArgumentException
     */
    public function create_user_provider($provider = null)
    {
        if (is_null($config = $this->get_provider_configuration($provider))) {
            return;
        }
        if (isset($this->custom_provider_creators[$driver = $config['driver'] ?? null])) {
            return call_user_func($this->custom_provider_creators[$driver], $this->app, $config);
        }
        return match ($driver) {
            'database' => $this->create_database_provider($config),
            'eloquent' => $this->create_eloquent_provider($config),
            default => throw new InvalidArgumentException("Authentication user provider [{$driver}] is not defined."),
        };
    }
    /**
     * Get the user provider configuration.
     *
     * @param  string|null  $provider
     * @return array|null
     */
    protected function get_provider_configuration($provider)
    {
        if ($provider = $provider ?: $this->get_default_user_provider()) {
            return $this->app['config']['auth.providers.' . $provider];
        }
    }
    /**
     * Create an instance of the database user provider.
     */
    protected function create_database_provider(array $config): \Illuminate\Auth\Database_User_Provider
    {
        return new Database_User_Provider($this->app['db']->connection($config['connection'] ?? null), $this->app['hash'], $config['table']);
    }
    /**
     * Create an instance of the Eloquent user provider.
     */
    protected function create_eloquent_provider(array $config): \Illuminate\Auth\Eloquent_User_Provider
    {
        return new Eloquent_User_Provider($this->app['hash'], $config['model']);
    }
    /**
     * Get the default user provider name.
     *
     * @return string
     */
    public function get_default_user_provider()
    {
        return $this->app['config']['auth.defaults.provider'];
    }
}