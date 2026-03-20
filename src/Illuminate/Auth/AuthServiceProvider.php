<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\Middleware\Require_Password;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Routing\Response_Factory;
use Illuminate\Contracts\Routing\Url_Generator;
use Illuminate\Support\Service_Provider;
class Auth_Service_Provider extends Service_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->register_authenticator();
        $this->register_user_resolver();
        $this->register_access_gate();
        $this->register_require_password();
        $this->register_request_rebind_handler();
        $this->register_event_rebind_handler();
    }
    /**
     * Register the authenticator services.
     *
     * @return void
     */
    protected function register_authenticator()
    {
        $this->app->singleton('auth', fn($app): \Illuminate\Auth\Auth_Manager => new Auth_Manager($app));
        $this->app->singleton('auth.driver', fn($app) => $app['auth']->guard());
    }
    /**
     * Register a resolver for the authenticated user.
     *
     * @return void
     */
    protected function register_user_resolver()
    {
        $this->app->bind(Authenticatable_Contract::class, fn($app): mixed => call_user_func($app['auth']->user_resolver()));
    }
    /**
     * Register the access gate service.
     *
     * @return void
     */
    protected function register_access_gate()
    {
        $this->app->singleton(Gate_Contract::class, fn($app): \Illuminate\Auth\Access\Gate => new Gate($app, fn(): mixed => call_user_func($app['auth']->user_resolver())));
    }
    /**
     * Register a resolver for the authenticated user.
     *
     * @return void
     */
    protected function register_require_password()
    {
        $this->app->bind(Require_Password::class, fn($app): \Illuminate\Auth\Middleware\Require_Password => new Require_Password($app[Response_Factory::class], $app[Url_Generator::class], $app['config']->get('auth.password_timeout')));
    }
    /**
     * Handle the re-binding of the request binding.
     *
     * @return void
     */
    protected function register_request_rebind_handler()
    {
        $this->app->rebinding('request', function ($app, $request): void {
            $request->set_user_resolver(fn($guard = null): mixed => call_user_func($app['auth']->user_resolver(), $guard));
        });
    }
    /**
     * Handle the re-binding of the event dispatcher binding.
     *
     * @return void
     */
    protected function register_event_rebind_handler()
    {
        $this->app->rebinding('events', function (array $app, $dispatcher): void {
            if (!$app->resolved('auth') || $app['auth']->has_resolved_guards() === false) {
                return;
            }
            if (method_exists($guard = $app['auth']->guard(), 'setDispatcher')) {
                $guard->set_dispatcher($dispatcher);
            }
        });
    }
}