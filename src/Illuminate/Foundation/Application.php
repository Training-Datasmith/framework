<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Closure;
use Composer\Autoload\Class_Loader;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Contracts\Foundation\Caches_Configuration;
use Illuminate\Contracts\Foundation\Caches_Routes;
use Illuminate\Contracts\Foundation\Maintenance_Mode as MaintenanceModeContract;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Events\Event_Service_Provider;
use Illuminate\Filesystem\Filesystem;
use function Illuminate\Filesystem\join_paths;
use Illuminate\Foundation\Bootstrap\Load_Environment_Variables;
use Illuminate\Foundation\Events\Locale_Updated;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Context_Service_Provider;
use Illuminate\Log\Log_Service_Provider;
use Illuminate\Routing\Routing_Service_Provider;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Service_Provider;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use RuntimeException;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Http_Foundation\Request as SymfonyRequest;
use Symfony\Component\Http_Foundation\Response as SymfonyResponse;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
class Application extends Container implements Application_Contract, Caches_Configuration, Caches_Routes, Http_Kernel_Interface
{
    use Macroable;
    /**
     * The Laravel framework version.
     *
     * @var string
     */
    public const VERSION = '12.53.0';
    /**
     * The base path for the Laravel installation.
     *
     * @var string
     */
    protected $base_path;
    /**
     * The array of registered callbacks.
     *
     * @var callable[]
     */
    protected $registered_callbacks = [];
    /**
     * Indicates if the application has been bootstrapped before.
     *
     * @var bool
     */
    protected $has_been_bootstrapped = false;
    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;
    /**
     * The array of booting callbacks.
     *
     * @var callable[]
     */
    protected $booting_callbacks = [];
    /**
     * The array of booted callbacks.
     *
     * @var callable[]
     */
    protected $booted_callbacks = [];
    /**
     * The array of terminating callbacks.
     *
     * @var callable[]
     */
    protected $terminating_callbacks = [];
    /**
     * All of the registered service providers.
     *
     * @var array<string, \Illuminate\Support\ServiceProvider>
     */
    protected $service_providers = [];
    /**
     * The names of the loaded service providers.
     *
     * @var array
     */
    protected $loaded_providers = [];
    /**
     * The deferred services and their providers.
     *
     * @var array
     */
    protected $deferred_services = [];
    /**
     * The custom bootstrap path defined by the developer.
     *
     * @var string
     */
    protected $bootstrap_path;
    /**
     * The custom application path defined by the developer.
     *
     * @var string
     */
    protected $app_path;
    /**
     * The custom configuration path defined by the developer.
     *
     * @var string
     */
    protected $config_path;
    /**
     * The custom database path defined by the developer.
     *
     * @var string
     */
    protected $database_path;
    /**
     * The custom language file path defined by the developer.
     *
     * @var string
     */
    protected $lang_path;
    /**
     * The custom public / web path defined by the developer.
     *
     * @var string
     */
    protected $public_path;
    /**
     * The custom storage path defined by the developer.
     *
     * @var string
     */
    protected $storage_path;
    /**
     * The custom environment path defined by the developer.
     *
     * @var string
     */
    protected $environment_path;
    /**
     * The environment file to load during bootstrapping.
     *
     * @var string
     */
    protected $environment_file = '.env';
    /**
     * Indicates if the application is running in the console.
     *
     * @var bool|null
     */
    protected $is_running_in_console;
    /**
     * The application namespace.
     *
     * @var string
     */
    protected $namespace;
    /**
     * Indicates if the framework's base configuration should be merged.
     *
     * @var bool
     */
    protected $merge_framework_configuration = true;
    /**
     * The prefixes of absolute cache paths for use during normalization.
     *
     * @var string[]
     */
    protected $absolute_cache_path_prefixes = ['/', '\\'];
    /**
     * Create a new Illuminate application instance.
     *
     * @param  string|null  $basePath
     */
    public function __construct($base_path = null)
    {
        if ($base_path) {
            $this->set_base_path($base_path);
        }
        $this->register_base_bindings();
        $this->register_base_service_providers();
        $this->register_core_container_aliases();
        $this->register_laravel_cloud_services();
    }
    /**
     * Begin configuring a new Laravel application instance.
     */
    public static function configure(?string $base_path = null): \Illuminate\Foundation\Configuration\Application_Builder
    {
        $base_path = match (true) {
            is_string($base_path) => $base_path,
            default => static::infer_base_path(),
        };
        return (new Configuration\Application_Builder(new static($base_path)))->with_kernels()->with_events()->with_commands()->with_providers();
    }
    /**
     * Infer the application's base directory from the environment.
     *
     * @return string
     */
    public static function infer_base_path()
    {
        return match (true) {
            isset($_ENV['APP_BASE_PATH']) => $_ENV['APP_BASE_PATH'],
            isset($_SERVER['APP_BASE_PATH']) => $_SERVER['APP_BASE_PATH'],
            default => dirname(array_values(array_filter(array_keys(Class_Loader::get_registered_loaders()), fn(string $path): bool => !str_starts_with($path, 'phar://')))[0]),
        };
    }
    /**
     * Get the version number of the application.
     */
    public function version(): string
    {
        return static::VERSION;
    }
    /**
     * Register the basic bindings into the container.
     *
     * @return void
     */
    protected function register_base_bindings()
    {
        static::set_instance($this);
        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->singleton(Mix::class);
        $this->singleton(Package_Manifest::class, fn(): \Illuminate\Foundation\Package_Manifest => new Package_Manifest(new Filesystem(), $this->base_path(), $this->get_cached_packages_path()));
    }
    /**
     * Register all of the base service providers.
     *
     * @return void
     */
    protected function register_base_service_providers()
    {
        $this->register(new Event_Service_Provider($this));
        $this->register(new Log_Service_Provider($this));
        $this->register(new Context_Service_Provider($this));
        $this->register(new Routing_Service_Provider($this));
    }
    /**
     * Register any services needed for Laravel Cloud.
     *
     * @return void
     */
    protected function register_laravel_cloud_services()
    {
        if (!laravel_cloud()) {
            return;
        }
        $this['events']->listen('bootstrapping: *', fn($bootstrapper) => Cloud::bootstrapper_bootstrapping($this, Str::after($bootstrapper, 'bootstrapping: ')));
        $this['events']->listen('bootstrapped: *', fn($bootstrapper) => Cloud::bootstrapper_bootstrapped($this, Str::after($bootstrapper, 'bootstrapped: ')));
    }
    /**
     * Run the given array of bootstrap classes.
     *
     * @param  string[]  $bootstrappers
     */
    public function bootstrap_with(array $bootstrappers): void
    {
        $this->has_been_bootstrapped = true;
        foreach ($bootstrappers as $bootstrapper) {
            $this['events']->dispatch('bootstrapping: ' . $bootstrapper, [$this]);
            $this->make($bootstrapper)->bootstrap($this);
            $this['events']->dispatch('bootstrapped: ' . $bootstrapper, [$this]);
        }
    }
    /**
     * Register a callback to run after loading the environment.
     */
    public function after_loading_environment(Closure $callback): void
    {
        $this->after_bootstrapping(Load_Environment_Variables::class, $callback);
    }
    /**
     * Register a callback to run before a bootstrapper.
     */
    public function before_bootstrapping(string $bootstrapper, Closure $callback): void
    {
        $this['events']->listen('bootstrapping: ' . $bootstrapper, $callback);
    }
    /**
     * Register a callback to run after a bootstrapper.
     */
    public function after_bootstrapping(string $bootstrapper, Closure $callback): void
    {
        $this['events']->listen('bootstrapped: ' . $bootstrapper, $callback);
    }
    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function has_been_bootstrapped()
    {
        return $this->has_been_bootstrapped;
    }
    /**
     * Set the base path for the application.
     *
     * @param  string  $basePath
     * @return $this
     */
    public function set_base_path($base_path): static
    {
        $this->base_path = rtrim($base_path, '\/');
        $this->bind_paths_in_container();
        return $this;
    }
    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bind_paths_in_container()
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->base_path());
        $this->instance('path.config', $this->config_path());
        $this->instance('path.database', $this->database_path());
        $this->instance('path.public', $this->public_path());
        $this->instance('path.resources', $this->resource_path());
        $this->instance('path.storage', $this->storage_path());
        $this->use_bootstrap_path(value(fn() => is_dir($directory = $this->base_path('.laravel')) ? $directory : $this->base_path('bootstrap')));
        $this->use_lang_path(value(fn() => is_dir($directory = $this->resource_path('lang')) ? $directory : $this->base_path('lang')));
    }
    /**
     * Get the path to the application "app" directory.
     *
     * @param  string  $path
     */
    public function path($path = ''): string
    {
        return $this->join_paths($this->app_path ?: $this->base_path('app'), $path);
    }
    /**
     * Set the application directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_app_path($path): static
    {
        $this->app_path = $path;
        $this->instance('path', $path);
        return $this;
    }
    /**
     * Get the base path of the Laravel installation.
     *
     * @param  string  $path
     */
    public function base_path($path = ''): string
    {
        return $this->join_paths($this->base_path, $path);
    }
    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path
     */
    public function bootstrap_path($path = ''): string
    {
        return $this->join_paths($this->bootstrap_path, $path);
    }
    /**
     * Get the path to the service provider list in the bootstrap directory.
     *
     * @return string
     */
    public function get_bootstrap_providers_path()
    {
        return $this->bootstrap_path('providers.php');
    }
    /**
     * Set the bootstrap file directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_bootstrap_path($path): static
    {
        $this->bootstrap_path = $path;
        $this->instance('path.bootstrap', $path);
        return $this;
    }
    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path
     */
    public function config_path($path = ''): string
    {
        return $this->join_paths($this->config_path ?: $this->base_path('config'), $path);
    }
    /**
     * Set the configuration directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_config_path($path): static
    {
        $this->config_path = $path;
        $this->instance('path.config', $path);
        return $this;
    }
    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     */
    public function database_path($path = ''): string
    {
        return $this->join_paths($this->database_path ?: $this->base_path('database'), $path);
    }
    /**
     * Set the database directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_database_path($path): static
    {
        $this->database_path = $path;
        $this->instance('path.database', $path);
        return $this;
    }
    /**
     * Get the path to the language files.
     *
     * @param  string  $path
     */
    public function lang_path($path = ''): string
    {
        return $this->join_paths($this->lang_path, $path);
    }
    /**
     * Set the language file directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_lang_path($path): static
    {
        $this->lang_path = $path;
        $this->instance('path.lang', $path);
        return $this;
    }
    /**
     * Get the path to the public / web directory.
     *
     * @param  string  $path
     */
    public function public_path($path = ''): string
    {
        return $this->join_paths($this->public_path ?: $this->base_path('public'), $path);
    }
    /**
     * Set the public / web directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_public_path($path): static
    {
        $this->public_path = $path;
        $this->instance('path.public', $path);
        return $this;
    }
    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     */
    public function storage_path($path = ''): string
    {
        if (isset($_ENV['LARAVEL_STORAGE_PATH'])) {
            return $this->join_paths($this->storage_path ?: $_ENV['LARAVEL_STORAGE_PATH'], $path);
        }
        if (isset($_SERVER['LARAVEL_STORAGE_PATH'])) {
            return $this->join_paths($this->storage_path ?: $_SERVER['LARAVEL_STORAGE_PATH'], $path);
        }
        return $this->join_paths($this->storage_path ?: $this->base_path('storage'), $path);
    }
    /**
     * Set the storage directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_storage_path($path): static
    {
        $this->storage_path = $path;
        $this->instance('path.storage', $path);
        return $this;
    }
    /**
     * Get the path to the resources directory.
     *
     * @param  string  $path
     */
    public function resource_path($path = ''): string
    {
        return $this->join_paths($this->base_path('resources'), $path);
    }
    /**
     * Get the path to the views directory.
     *
     * This method returns the first configured path in the array of view paths.
     *
     * @param  string  $path
     */
    public function view_path($path = ''): string
    {
        $view_path = rtrim((string) $this['config']->get('view.paths')[0], DIRECTORY_SEPARATOR);
        return $this->join_paths($view_path, $path);
    }
    /**
     * Join the given paths together.
     *
     * @param  string  $basePath
     * @param  string  $path
     */
    public function join_paths($base_path, $path = ''): string
    {
        return join_paths($base_path, $path);
    }
    /**
     * Get the path to the environment file directory.
     *
     * @return string
     */
    public function environment_path()
    {
        return $this->environment_path ?: $this->base_path;
    }
    /**
     * Set the directory for the environment file.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_environment_path($path): static
    {
        $this->environment_path = $path;
        return $this;
    }
    /**
     * Set the environment file to be loaded during bootstrapping.
     *
     * @param  string  $file
     * @return $this
     */
    public function load_environment_from($file): static
    {
        $this->environment_file = $file;
        return $this;
    }
    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environment_file()
    {
        return $this->environment_file ?: '.env';
    }
    /**
     * Get the fully-qualified path to the environment file.
     */
    public function environment_file_path(): string
    {
        return $this->environment_path() . DIRECTORY_SEPARATOR . $this->environment_file();
    }
    /**
     * Get or check the current application environment.
     *
     * @param  string|array  ...$environments
     * @return string|bool
     */
    public function environment(...$environments)
    {
        if (count($environments) > 0) {
            $patterns = is_array($environments[0]) ? $environments[0] : $environments;
            return Str::is($patterns, $this['env']);
        }
        return $this['env'];
    }
    /**
     * Determine if the application is in the local environment.
     */
    public function is_local(): bool
    {
        return $this['env'] === 'local';
    }
    /**
     * Determine if the application is in the production environment.
     */
    public function is_production(): bool
    {
        return $this['env'] === 'production';
    }
    /**
     * Detect the application's current environment.
     *
     * @return string
     */
    public function detect_environment(Closure $callback)
    {
        $args = $this->running_in_console() && isset($_SERVER['argv']) ? $_SERVER['argv'] : null;
        return $this['env'] = (new Environment_Detector())->detect($callback, $args);
    }
    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function running_in_console()
    {
        if ($this->is_running_in_console === null) {
            $this->is_running_in_console = Env::get('APP_RUNNING_IN_CONSOLE') ?? \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
        }
        return $this->is_running_in_console;
    }
    /**
     * Determine if the application is running any of the given console commands.
     *
     * @param  string|array  ...$commands
     * @return bool
     */
    public function running_console_command(...$commands)
    {
        if (!$this->running_in_console()) {
            return false;
        }
        return in_array($_SERVER['argv'][1] ?? null, is_array($commands[0]) ? $commands[0] : $commands);
    }
    /**
     * Determine if the application is running unit tests.
     */
    public function running_unit_tests(): bool
    {
        return $this->bound('env') && $this['env'] === 'testing';
    }
    /**
     * Determine if the application is running with debug mode enabled.
     */
    public function has_debug_mode_enabled(): bool
    {
        return (bool) $this['config']->get('app.debug');
    }
    /**
     * Register a new registered listener.
     *
     * @param  callable  $callback
     */
    public function registered($callback): void
    {
        $this->registered_callbacks[] = $callback;
    }
    /**
     * Register all of the configured providers.
     */
    public function register_configured_providers(): void
    {
        $providers = (new Collection($this->make('config')->get('app.providers')))->partition(fn($provider): bool => str_starts_with((string) $provider, 'Illuminate\\'));
        $providers->splice(1, 0, [$this->make(Package_Manifest::class)->providers()]);
        (new Provider_Repository($this, new Filesystem(), $this->get_cached_services_path()))->load($providers->collapse()->to_array());
        $this->fire_app_callbacks($this->registered_callbacks);
    }
    /**
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @param  bool  $force
     * @return \Illuminate\Support\ServiceProvider
     */
    public function register($provider, $force = false)
    {
        if (($registered = $this->get_provider($provider)) && !$force) {
            return $registered;
        }
        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolve_provider($provider);
        }
        $provider->register();
        // If there are bindings / singletons set as properties on the provider we
        // will spin through them and register them with the application, which
        // serves as a convenience layer while registering a lot of bindings.
        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }
        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $key = is_int($key) ? $value : $key;
                $this->singleton($key, $value);
            }
        }
        $this->mark_as_registered($provider);
        // If the application has already booted, we will call this boot method on
        // the provider class so it has an opportunity to do its boot logic and
        // will be ready for any usage by this developer's application logic.
        if ($this->is_booted()) {
            $this->boot_provider($provider);
        }
        return $provider;
    }
    /**
     * Get the registered service provider instance if it exists.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @return \Illuminate\Support\ServiceProvider|null
     */
    public function get_provider($provider)
    {
        $name = is_string($provider) ? $provider : $provider::class;
        return $this->service_providers[$name] ?? null;
    }
    /**
     * Get the registered service provider instances if any exist.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     */
    public function get_providers($provider): array
    {
        $name = is_string($provider) ? $provider : $provider::class;
        return Arr::where($this->service_providers, fn($value): bool => $value instanceof $name);
    }
    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return \Illuminate\Support\ServiceProvider
     */
    public function resolve_provider($provider)
    {
        return new $provider($this);
    }
    /**
     * Mark the given provider as registered.
     *
     * @param  \Illuminate\Support\ServiceProvider  $provider
     * @return void
     */
    protected function mark_as_registered($provider)
    {
        $class = $provider::class;
        $this->service_providers[$class] = $provider;
        $this->loaded_providers[$class] = true;
    }
    /**
     * Load and boot all of the remaining deferred providers.
     */
    public function load_deferred_providers(): void
    {
        // We will simply spin through each of the deferred providers and register each
        // one and boot them if the application has booted. This should make each of
        // the remaining services available to this application for immediate use.
        foreach ($this->deferred_services as $service => $provider) {
            $this->load_deferred_provider($service);
        }
        $this->deferred_services = [];
    }
    /**
     * Load the provider for a deferred service.
     *
     * @param  string  $service
     */
    public function load_deferred_provider($service): void
    {
        if (!$this->is_deferred_service($service)) {
            return;
        }
        $provider = $this->deferred_services[$service];
        // If the service provider has not already been loaded and registered we can
        // register it with the application and remove the service from this list
        // of deferred services, since it will already be loaded on subsequent.
        if (!isset($this->loaded_providers[$provider])) {
            $this->register_deferred_provider($provider, $service);
        }
    }
    /**
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     */
    public function register_deferred_provider($provider, $service = null): void
    {
        // Once the provider that provides the deferred service has been registered we
        // will remove it from our local list of the deferred services with related
        // providers so that this container does not try to resolve it out again.
        if ($service) {
            unset($this->deferred_services[$service]);
        }
        $this->register($instance = new $provider($this));
        if (!$this->is_booted()) {
            $this->booting(function () use ($instance): void {
                $this->boot_provider($instance);
            });
        }
    }
    /**
     * Resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>  $abstract
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function make($abstract, array $parameters = [])
    {
        $this->load_deferred_provider_if_needed($abstract = $this->get_alias($abstract));
        return parent::make($abstract, $parameters);
    }
    /**
     * Resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>|callable  $abstract
     * @param  array  $parameters
     * @param  bool  $raiseEvents
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Illuminate\Contracts\Container\CircularDependencyException
     */
    protected function resolve($abstract, $parameters = [], $raise_events = true)
    {
        $this->load_deferred_provider_if_needed($abstract = $this->get_alias($abstract));
        return parent::resolve($abstract, $parameters, $raise_events);
    }
    /**
     * Load the deferred provider if the given type is a deferred service and the instance has not been loaded.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function load_deferred_provider_if_needed($abstract)
    {
        if ($this->is_deferred_service($abstract) && !isset($this->instances[$abstract])) {
            $this->load_deferred_provider($abstract);
        }
    }
    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string  $abstract
     */
    public function bound($abstract): bool
    {
        if ($this->is_deferred_service($abstract)) {
            return true;
        }
        return parent::bound($abstract);
    }
    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function is_booted()
    {
        return $this->booted;
    }
    /**
     * Boot the application's service providers.
     */
    public function boot(): void
    {
        if ($this->is_booted()) {
            return;
        }
        // Once the application has booted we will also fire some "booted" callbacks
        // for any listeners that need to do work after this initial booting gets
        // finished. This is useful when ordering the boot-up processes we run.
        $this->fire_app_callbacks($this->booting_callbacks);
        array_walk($this->service_providers, function (\Illuminate\Support\Service_Provider $p): void {
            $this->boot_provider($p);
        });
        $this->booted = true;
        $this->fire_app_callbacks($this->booted_callbacks);
    }
    /**
     * Boot the given service provider.
     *
     * @return void
     */
    protected function boot_provider(Service_Provider $provider)
    {
        $provider->call_booting_callbacks();
        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }
        $provider->call_booted_callbacks();
    }
    /**
     * Register a new boot listener.
     *
     * @param  callable  $callback
     */
    public function booting($callback): void
    {
        $this->booting_callbacks[] = $callback;
    }
    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     */
    public function booted($callback): void
    {
        $this->booted_callbacks[] = $callback;
        if ($this->is_booted()) {
            $callback($this);
        }
    }
    /**
     * Call the booting callbacks for the application.
     *
     * @param  callable[]  $callbacks
     * @return void
     */
    protected function fire_app_callbacks(array &$callbacks)
    {
        $index = 0;
        while ($index < count($callbacks)) {
            $callbacks[$index]($this);
            $index++;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function handle(Symfony_Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Symfony_Response
    {
        return $this[Http_Kernel_Contract::class]->handle(Request::create_from_base($request));
    }
    /**
     * Handle the incoming HTTP request and send the response to the browser.
     */
    public function handle_request(Request $request): void
    {
        $kernel = $this->make(Http_Kernel_Contract::class);
        $response = $kernel->handle($request)->send();
        $kernel->terminate($request, $response);
    }
    /**
     * Handle the incoming Artisan command.
     *
     * @return int
     */
    public function handle_command(Input_Interface $input)
    {
        $kernel = $this->make(Console_Kernel_Contract::class);
        $status = $kernel->handle($input, new Console_Output());
        $kernel->terminate($input, $status);
        return $status;
    }
    /**
     * Determine if the framework's base configuration should be merged.
     *
     * @return bool
     */
    public function should_merge_framework_configuration()
    {
        return $this->merge_framework_configuration;
    }
    /**
     * Indicate that the framework's base configuration should not be merged.
     *
     * @return $this
     */
    public function dont_merge_framework_configuration(): static
    {
        $this->merge_framework_configuration = false;
        return $this;
    }
    /**
     * Determine if middleware has been disabled for the application.
     */
    public function should_skip_middleware(): bool
    {
        return $this->bound('middleware.disable') && $this->make('middleware.disable') === true;
    }
    /**
     * Get the path to the cached services.php file.
     *
     * @return string
     */
    public function get_cached_services_path()
    {
        return $this->normalize_cache_path('APP_SERVICES_CACHE', 'cache/services.php');
    }
    /**
     * Get the path to the cached packages.php file.
     *
     * @return string
     */
    public function get_cached_packages_path()
    {
        return $this->normalize_cache_path('APP_PACKAGES_CACHE', 'cache/packages.php');
    }
    /**
     * Determine if the application configuration is cached.
     *
     * @return bool
     */
    public function configuration_is_cached()
    {
        if ($this->bound('config_loaded_from_cache')) {
            return (bool) $this->make('config_loaded_from_cache');
        }
        return $this->instance('config_loaded_from_cache', is_file($this->get_cached_config_path()));
    }
    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function get_cached_config_path()
    {
        return $this->normalize_cache_path('APP_CONFIG_CACHE', 'cache/config.php');
    }
    /**
     * Determine if the application routes are cached.
     *
     * @return bool
     */
    public function routes_are_cached()
    {
        if ($this->bound('routes.cached')) {
            return (bool) $this->make('routes.cached');
        }
        return $this->instance('routes.cached', $this['files']->exists($this->get_cached_routes_path()));
    }
    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */
    public function get_cached_routes_path()
    {
        return $this->normalize_cache_path('APP_ROUTES_CACHE', 'cache/routes-v7.php');
    }
    /**
     * Determine if the application events are cached.
     *
     * @return bool
     */
    public function events_are_cached()
    {
        if ($this->bound('events.cached')) {
            return (bool) $this->make('events.cached');
        }
        return $this->instance('events.cached', $this['files']->exists($this->get_cached_events_path()));
    }
    /**
     * Get the path to the events cache file.
     *
     * @return string
     */
    public function get_cached_events_path()
    {
        return $this->normalize_cache_path('APP_EVENTS_CACHE', 'cache/events.php');
    }
    /**
     * Normalize a relative or absolute path to a cache file.
     *
     * @param  string  $key
     * @param  string  $default
     * @return string
     */
    protected function normalize_cache_path($key, $default)
    {
        if (is_null($env = Env::get($key))) {
            return $this->bootstrap_path($default);
        }
        return Str::starts_with($env, $this->absolute_cache_path_prefixes) ? $env : $this->base_path($env);
    }
    /**
     * Add new prefix to list of absolute path prefixes.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function add_absolute_cache_path_prefix($prefix): static
    {
        $this->absolute_cache_path_prefixes[] = $prefix;
        return $this;
    }
    /**
     * Get an instance of the maintenance mode manager implementation.
     *
     * @return \Illuminate\Contracts\Foundation\MaintenanceMode
     */
    public function maintenance_mode()
    {
        return $this->make(Maintenance_Mode_Contract::class);
    }
    /**
     * Determine if the application is currently down for maintenance.
     */
    public function is_down_for_maintenance(): bool
    {
        return $this->maintenance_mode()->active();
    }
    /**
     * Throw an HttpException with the given data.
     *
     * @param  int  $code
     * @param  string  $message
     * @return never
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function abort($code, $message = '', array $headers = []): void
    {
        if ($code == 404) {
            throw new Not_Found_Http_Exception($message, null, 0, $headers);
        }
        throw new Http_Exception($code, $message, null, $headers);
    }
    /**
     * Register a terminating callback with the application.
     *
     * @param  callable|string  $callback
     * @return $this
     */
    public function terminating($callback): static
    {
        $this->terminating_callbacks[] = $callback;
        return $this;
    }
    /**
     * Terminate the application.
     */
    public function terminate(): void
    {
        $index = 0;
        while ($index < count($this->terminating_callbacks)) {
            $this->call($this->terminating_callbacks[$index]);
            $index++;
        }
    }
    /**
     * Get the service providers that have been loaded.
     *
     * @return array<string, bool>
     */
    public function get_loaded_providers()
    {
        return $this->loaded_providers;
    }
    /**
     * Determine if the given service provider is loaded.
     */
    public function provider_is_loaded(string $provider): bool
    {
        return isset($this->loaded_providers[$provider]);
    }
    /**
     * Get the application's deferred services.
     *
     * @return array
     */
    public function get_deferred_services()
    {
        return $this->deferred_services;
    }
    /**
     * Set the application's deferred services.
     */
    public function set_deferred_services(array $services): void
    {
        $this->deferred_services = $services;
    }
    /**
     * Determine if the given service is a deferred service.
     *
     * @param  string  $service
     */
    public function is_deferred_service($service): bool
    {
        return isset($this->deferred_services[$service]);
    }
    /**
     * Add an array of services to the application's deferred services.
     */
    public function add_deferred_services(array $services): void
    {
        $this->deferred_services = array_merge($this->deferred_services, $services);
    }
    /**
     * Remove an array of services from the application's deferred services.
     */
    public function remove_deferred_services(array $services): void
    {
        foreach ($services as $service) {
            unset($this->deferred_services[$service]);
        }
    }
    /**
     * Configure the real-time facade namespace.
     *
     * @param  string  $namespace
     */
    public function provide_facades($namespace): void
    {
        Alias_Loader::set_facade_namespace($namespace);
    }
    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function get_locale()
    {
        return $this['config']->get('app.locale');
    }
    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function current_locale()
    {
        return $this->get_locale();
    }
    /**
     * Get the current application fallback locale.
     *
     * @return string
     */
    public function get_fallback_locale()
    {
        return $this['config']->get('app.fallback_locale');
    }
    /**
     * Set the current application locale.
     *
     * @param  string  $locale
     */
    public function set_locale($locale): void
    {
        $previous = $this['config']->get('app.locale');
        $this['config']->set('app.locale', $locale);
        $this['translator']->set_locale($locale);
        $this['events']->dispatch(new Locale_Updated($locale, $previous));
    }
    /**
     * Set the current application fallback locale.
     *
     * @param  string  $fallbackLocale
     */
    public function set_fallback_locale($fallback_locale): void
    {
        $this['config']->set('app.fallback_locale', $fallback_locale);
        $this['translator']->set_fallback($fallback_locale);
    }
    /**
     * Determine if the application locale is the given locale.
     *
     * @param  string  $locale
     */
    public function is_locale($locale): bool
    {
        return $this->get_locale() == $locale;
    }
    /**
     * Register the core class aliases in the container.
     */
    public function register_core_container_aliases(): void
    {
        foreach (['app' => [self::class, \Illuminate\Contracts\Container\Container::class, \Illuminate\Contracts\Foundation\Application::class, \Psr\Container\Container_Interface::class], 'auth' => [\Illuminate\Auth\Auth_Manager::class, \Illuminate\Contracts\Auth\Factory::class], 'auth.driver' => [\Illuminate\Contracts\Auth\Guard::class], 'auth.password' => [\Illuminate\Auth\Passwords\Password_Broker_Manager::class, \Illuminate\Contracts\Auth\Password_Broker_Factory::class], 'auth.password.broker' => [\Illuminate\Auth\Passwords\Password_Broker::class, \Illuminate\Contracts\Auth\Password_Broker::class], 'blade.compiler' => [\Illuminate\View\Compilers\Blade_Compiler::class], 'cache' => [\Illuminate\Cache\Cache_Manager::class, \Illuminate\Contracts\Cache\Factory::class], 'cache.store' => [\Illuminate\Cache\Repository::class, \Illuminate\Contracts\Cache\Repository::class, \Psr\Simple_Cache\Cache_Interface::class], 'cache.psr6' => [\Symfony\Component\Cache\Adapter\Psr16Adapter::class, \Symfony\Component\Cache\Adapter\Adapter_Interface::class, \Psr\Cache\Cache_Item_Pool_Interface::class], 'config' => [\Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class], 'cookie' => [\Illuminate\Cookie\Cookie_Jar::class, \Illuminate\Contracts\Cookie\Factory::class, \Illuminate\Contracts\Cookie\Queueing_Factory::class], 'db' => [\Illuminate\Database\Database_Manager::class, \Illuminate\Database\Connection_Resolver_Interface::class], 'db.connection' => [\Illuminate\Database\Connection::class, \Illuminate\Database\Connection_Interface::class], 'db.schema' => [\Illuminate\Database\Schema\Builder::class], 'encrypter' => [\Illuminate\Encryption\Encrypter::class, \Illuminate\Contracts\Encryption\Encrypter::class, \Illuminate\Contracts\Encryption\String_Encrypter::class], 'events' => [\Illuminate\Events\Dispatcher::class, \Illuminate\Contracts\Events\Dispatcher::class], 'files' => [\Illuminate\Filesystem\Filesystem::class], 'filesystem' => [\Illuminate\Filesystem\Filesystem_Manager::class, \Illuminate\Contracts\Filesystem\Factory::class], 'filesystem.disk' => [\Illuminate\Contracts\Filesystem\Filesystem::class], 'filesystem.cloud' => [\Illuminate\Contracts\Filesystem\Cloud::class], 'hash' => [\Illuminate\Hashing\Hash_Manager::class], 'hash.driver' => [\Illuminate\Contracts\Hashing\Hasher::class], 'log' => [\Illuminate\Log\Log_Manager::class, \Psr\Log\Logger_Interface::class], 'mail.manager' => [\Illuminate\Mail\Mail_Manager::class, \Illuminate\Contracts\Mail\Factory::class], 'mailer' => [\Illuminate\Mail\Mailer::class, \Illuminate\Contracts\Mail\Mailer::class, \Illuminate\Contracts\Mail\Mail_Queue::class], 'queue' => [\Illuminate\Queue\Queue_Manager::class, \Illuminate\Contracts\Queue\Factory::class, \Illuminate\Contracts\Queue\Monitor::class], 'queue.connection' => [\Illuminate\Contracts\Queue\Queue::class], 'queue.failer' => [\Illuminate\Queue\Failed\Failed_Job_Provider_Interface::class], 'redirect' => [\Illuminate\Routing\Redirector::class], 'redis' => [\Illuminate\Redis\Redis_Manager::class, \Illuminate\Contracts\Redis\Factory::class], 'redis.connection' => [\Illuminate\Redis\Connections\Connection::class, \Illuminate\Contracts\Redis\Connection::class], 'request' => [\Illuminate\Http\Request::class, \Symfony\Component\Http_Foundation\Request::class], 'router' => [\Illuminate\Routing\Router::class, \Illuminate\Contracts\Routing\Registrar::class, \Illuminate\Contracts\Routing\Binding_Registrar::class], 'session' => [\Illuminate\Session\Session_Manager::class], 'session.store' => [\Illuminate\Session\Store::class, \Illuminate\Contracts\Session\Session::class], 'translator' => [\Illuminate\Translation\Translator::class, \Illuminate\Contracts\Translation\Translator::class], 'url' => [\Illuminate\Routing\Url_Generator::class, \Illuminate\Contracts\Routing\Url_Generator::class], 'validator' => [\Illuminate\Validation\Factory::class, \Illuminate\Contracts\Validation\Factory::class], 'view' => [\Illuminate\View\Factory::class, \Illuminate\Contracts\View\Factory::class]] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }
    /**
     * Flush the container of all bindings and resolved instances.
     */
    public function flush(): void
    {
        parent::flush();
        $this->build_stack = [];
        $this->loaded_providers = [];
        $this->booted_callbacks = [];
        $this->booting_callbacks = [];
        $this->deferred_services = [];
        $this->rebound_callbacks = [];
        $this->service_providers = [];
        $this->resolving_callbacks = [];
        $this->terminating_callbacks = [];
        $this->before_resolving_callbacks = [];
        $this->after_resolving_callbacks = [];
        $this->global_before_resolving_callbacks = [];
        $this->global_resolving_callbacks = [];
        $this->global_after_resolving_callbacks = [];
    }
    /**
     * Get the application namespace.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function get_namespace()
    {
        if (!is_null($this->namespace)) {
            return $this->namespace;
        }
        $composer = json_decode(file_get_contents($this->base_path('composer.json')), true);
        foreach ((array) data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            foreach ((array) $path as $path_choice) {
                if (realpath($this->path()) === realpath($this->base_path($path_choice))) {
                    return $this->namespace = $namespace;
                }
            }
        }
        throw new RuntimeException('Unable to detect application namespace.');
    }
}