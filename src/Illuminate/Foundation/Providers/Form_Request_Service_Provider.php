<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Providers;

use Illuminate\Contracts\Validation\Validates_When_Resolved;
use Illuminate\Foundation\Http\Form_Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Service_Provider;
class Form_Request_Service_Provider extends Service_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
    }
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->app->after_resolving(Validates_When_Resolved::class, function ($resolved): void {
            $resolved->validate_resolved();
        });
        $this->app->resolving(Form_Request::class, function ($request, \Illuminate\Contracts\Container\Container $app): void {
            $request = Form_Request::create_from($app['request'], $request);
            $request->set_container($app)->set_redirector($app->make(Redirector::class));
        });
    }
}