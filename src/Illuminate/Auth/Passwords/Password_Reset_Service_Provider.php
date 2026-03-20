<?php

declare (strict_types=1);
namespace Illuminate\Auth\Passwords;

use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Support\Service_Provider;
class Password_Reset_Service_Provider extends Service_Provider implements Deferrable_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->register_password_broker();
    }
    /**
     * Register the password broker instance.
     *
     * @return void
     */
    protected function register_password_broker()
    {
        $this->app->singleton('auth.password', fn($app): \Illuminate\Auth\Passwords\Password_Broker_Manager => new Password_Broker_Manager($app));
        $this->app->bind('auth.password.broker', fn($app) => $app->make('auth.password')->broker());
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['auth.password', 'auth.password.broker'];
    }
}