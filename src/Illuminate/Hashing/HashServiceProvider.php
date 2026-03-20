<?php

declare (strict_types=1);
namespace Illuminate\Hashing;

use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Support\Service_Provider;
class Hash_Service_Provider extends Service_Provider implements Deferrable_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('hash', fn($app): \Illuminate\Hashing\Hash_Manager => new Hash_Manager($app));
        $this->app->singleton('hash.driver', fn($app) => $app['hash']->driver());
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['hash', 'hash.driver'];
    }
}