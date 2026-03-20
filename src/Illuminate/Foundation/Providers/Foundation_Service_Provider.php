<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Providers;

use Illuminate\Console\Events\Command_Finished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\Exception_Renderer;
use Illuminate\Contracts\Foundation\Maintenance_Mode as MaintenanceModeContract;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Connection_Interface;
use Illuminate\Database\Grammar;
use Illuminate\Foundation\Console\Cli_Dumper;
use Illuminate\Foundation\Exceptions\Renderer\Listener;
use Illuminate\Foundation\Exceptions\Renderer\Mappers\Blade_Mapper;
use Illuminate\Foundation\Exceptions\Renderer\Renderer;
use Illuminate\Foundation\Http\Html_Dumper;
use Illuminate\Foundation\Maintenance_Mode_Manager;
use Illuminate\Foundation\Precognition;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Log\Events\Message_Logged;
use Illuminate\Queue\Events\Job_Attempted;
use Illuminate\Support\Aggregate_Service_Provider;
use Illuminate\Support\Defer\Deferred_Callback_Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Uri;
use Illuminate\Testing\Logged_Exception_Collection;
use Illuminate\Testing\Parallel_Testing_Service_Provider;
use Illuminate\Validation\Validation_Exception;
use Symfony\Component\Error_Handler\Error_Renderer\Html_Error_Renderer;
use Symfony\Component\Var_Dumper\Caster\Stub_Caster;
use Symfony\Component\Var_Dumper\Cloner\Abstract_Cloner;
class Foundation_Service_Provider extends Aggregate_Service_Provider
{
    /**
     * The provider class names.
     *
     * @var string[]
     */
    protected $providers = [Form_Request_Service_Provider::class, Parallel_Testing_Service_Provider::class];
    /**
     * The singletons to register into the container.
     *
     * @var array
     */
    public $singletons = [Http_Factory::class => Http_Factory::class, Vite::class => Vite::class];
    /**
     * Boot the service provider.
     */
    public function boot(): void
    {
        if ($this->app->running_in_console()) {
            $this->publishes([__DIR__ . '/../Exceptions/views' => $this->app->resource_path('views/errors/')], 'laravel-errors');
        }
        if ($this->app->has_debug_mode_enabled() && !$this->app->has(Exception_Renderer::class)) {
            $this->app->make(Listener::class)->register_listeners($this->app->make(Dispatcher::class));
        }
    }
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        parent::register();
        $this->register_console_schedule();
        $this->register_dumper();
        $this->register_request_validation();
        $this->register_request_signature_validation();
        $this->register_uri_url_generation();
        $this->register_defer_handler();
        $this->register_exception_tracking();
        $this->register_exception_renderer();
        $this->register_maintenance_mode_manager();
    }
    /**
     * Register the console schedule implementation.
     */
    public function register_console_schedule(): void
    {
        $this->app->singleton(Schedule::class, fn($app) => $app->make(Console_Kernel::class)->resolve_console_schedule());
    }
    /**
     * Register a var dumper (with source) to debug variables.
     */
    public function register_dumper(): void
    {
        Abstract_Cloner::$default_casters[Connection_Interface::class] ??= [Stub_Caster::class, 'cutInternals'];
        Abstract_Cloner::$default_casters[Container::class] ??= [Stub_Caster::class, 'cutInternals'];
        Abstract_Cloner::$default_casters[Dispatcher::class] ??= [Stub_Caster::class, 'cutInternals'];
        Abstract_Cloner::$default_casters[Factory::class] ??= [Stub_Caster::class, 'cutInternals'];
        Abstract_Cloner::$default_casters[Grammar::class] ??= [Stub_Caster::class, 'cutInternals'];
        $base_path = $this->app->base_path();
        $compiled_view_path = $this->app['config']->get('view.compiled');
        $format = $_SERVER['VAR_DUMPER_FORMAT'] ?? null;
        match (true) {
            'html' == $format => Html_Dumper::register($base_path, $compiled_view_path),
            'cli' == $format => Cli_Dumper::register($base_path, $compiled_view_path),
            'server' == $format => null,
            $format && 'tcp' == parse_url((string) $format, PHP_URL_SCHEME) => null,
            default => in_array(PHP_SAPI, ['cli', 'phpdbg']) ? Cli_Dumper::register($base_path, $compiled_view_path) : Html_Dumper::register($base_path, $compiled_view_path),
        };
    }
    /**
     * Register the "validate" macro on the request.
     */
    public function register_request_validation(): void
    {
        Request::macro('validate', fn(array $rules, ...$params) => tap(validator($this->all(), $rules, ...$params), function ($validator): void {
            if ($this->is_precognitive()) {
                $validator->after(Precognition::after_validation_hook($this))->set_rules($this->filter_precognitive_rules($validator->get_rules_without_placeholders()));
            }
        })->validate());
        Request::macro('validateWithBag', function (string $error_bag, array $rules, ...$params) {
            try {
                return $this->validate($rules, ...$params);
            } catch (Validation_Exception $e) {
                $e->error_bag = $error_bag;
                throw $e;
            }
        });
    }
    /**
     * Register the "hasValidSignature" macro on the request.
     */
    public function register_request_signature_validation(): void
    {
        Request::macro('hasValidSignature', fn($absolute = true) => URL::has_valid_signature($this, $absolute));
        Request::macro('hasValidRelativeSignature', fn() => URL::has_valid_signature($this, $absolute = false));
        Request::macro('hasValidSignatureWhileIgnoring', fn($ignore_query = [], $absolute = true) => URL::has_valid_signature($this, $absolute, $ignore_query));
        Request::macro('hasValidRelativeSignatureWhileIgnoring', fn($ignore_query = []) => URL::has_valid_signature($this, $absolute = false, $ignore_query));
    }
    /**
     * Register the URL resolver for the URI generator.
     *
     * @return void
     */
    protected function register_uri_url_generation()
    {
        Uri::set_url_generator_resolver(fn() => app('url'));
    }
    /**
     * Register the "defer" function termination handler.
     *
     * @return void
     */
    protected function register_defer_handler()
    {
        $this->app->scoped(Deferred_Callback_Collection::class);
        $this->app['events']->listen(function (Command_Finished $event): void {
            app(Deferred_Callback_Collection::class)->invoke_when(fn($callback): bool => app()->running_in_console() && ($event->exit_code === 0 || $callback->always));
        });
        $this->app['events']->listen(function (Job_Attempted $event): void {
            if (in_array($event->connection_name, ['sync', 'deferred'])) {
                return;
            }
            app(Deferred_Callback_Collection::class)->invoke_when(fn($callback): bool => $event->successful() || $callback->always);
        });
    }
    /**
     * Register an event listener to track logged exceptions.
     *
     * @return void
     */
    protected function register_exception_tracking()
    {
        if (!$this->app->running_unit_tests()) {
            return;
        }
        $this->app->instance(Logged_Exception_Collection::class, new Logged_Exception_Collection());
        $this->app->make('events')->listen(Message_Logged::class, function ($event): void {
            if (isset($event->context['exception'])) {
                $this->app->make(Logged_Exception_Collection::class)->push($event->context['exception']);
            }
        });
    }
    /**
     * Register the exceptions renderer.
     *
     * @return void
     */
    protected function register_exception_renderer()
    {
        $this->load_views_from(__DIR__ . '/../Exceptions/views', 'laravel-exceptions');
        if (!$this->app->has_debug_mode_enabled()) {
            return;
        }
        $this->load_views_from(__DIR__ . '/../resources/exceptions/renderer', 'laravel-exceptions-renderer');
        $this->app->singleton(Renderer::class, function (Application $app): \Illuminate\Foundation\Exceptions\Renderer\Renderer {
            $error_renderer = new Html_Error_Renderer($app['config']->get('app.debug'));
            return new Renderer($app->make(Factory::class), $app->make(Listener::class), $error_renderer, $app->make(Blade_Mapper::class), $app->base_path());
        });
        $this->app->singleton(Listener::class);
    }
    /**
     * Register the maintenance mode manager service.
     */
    public function register_maintenance_mode_manager(): void
    {
        $this->app->singleton(Maintenance_Mode_Manager::class);
        $this->app->bind(Maintenance_Mode_Contract::class, fn() => $this->app->make(Maintenance_Mode_Manager::class)->driver());
    }
}