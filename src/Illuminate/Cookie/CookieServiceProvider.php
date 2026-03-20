<?php

declare (strict_types=1);
namespace Illuminate\Cookie;

use Illuminate\Support\Service_Provider;
class Cookie_Service_Provider extends Service_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('cookie', function ($app): \Illuminate\Cookie\Cookie_Jar {
            $config = $app->make('config')->get('session');
            return (new Cookie_Jar())->set_default_path_and_domain($config['path'], $config['domain'], $config['secure'], $config['same_site'] ?? null);
        });
    }
}