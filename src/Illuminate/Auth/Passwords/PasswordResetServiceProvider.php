<?php

namespace Illuminate\Auth\Passwords;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class PasswordResetServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->registerPasswordBroker();
    }

    /**
     * Register the password broker instance.
     *
     * @return void
     */
    protected function registerPasswordBroker()
    {
        $this->app->singleton('auth.password', fn($app) => new PasswordBrokerManager($app));

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
