<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Foundation;

use Illuminate\Contracts\Container\Container;
interface Application extends Container
{
    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version();
    /**
     * Get the base path of the Laravel installation.
     *
     * @param  string  $path
     * @return string
     */
    public function base_path($path = '');
    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path
     * @return string
     */
    public function bootstrap_path($path = '');
    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path
     * @return string
     */
    public function config_path($path = '');
    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    public function database_path($path = '');
    /**
     * Get the path to the language files.
     *
     * @param  string  $path
     * @return string
     */
    public function lang_path($path = '');
    /**
     * Get the path to the public directory.
     *
     * @param  string  $path
     * @return string
     */
    public function public_path($path = '');
    /**
     * Get the path to the resources directory.
     *
     * @param  string  $path
     * @return string
     */
    public function resource_path($path = '');
    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     * @return string
     */
    public function storage_path($path = '');
    /**
     * Get or check the current application environment.
     *
     * @param  string|array  ...$environments
     * @return string|bool
     */
    public function environment(...$environments);
    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function running_in_console();
    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function running_unit_tests();
    /**
     * Determine if the application is running with debug mode enabled.
     *
     * @return bool
     */
    public function has_debug_mode_enabled();
    /**
     * Get an instance of the maintenance mode manager implementation.
     *
     * @return \Illuminate\Contracts\Foundation\MaintenanceMode
     */
    public function maintenance_mode();
    /**
     * Determine if the application is currently down for maintenance.
     *
     * @return bool
     */
    public function is_down_for_maintenance();
    /**
     * Register all of the configured providers.
     *
     * @return void
     */
    public function register_configured_providers();
    /**
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @param  bool  $force
     * @return \Illuminate\Support\ServiceProvider
     */
    public function register($provider, $force = false);
    /**
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     * @return void
     */
    public function register_deferred_provider($provider, $service = null);
    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return \Illuminate\Support\ServiceProvider
     */
    public function resolve_provider($provider);
    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot();
    /**
     * Register a new boot listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booting($callback);
    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booted($callback);
    /**
     * Run the given array of bootstrap classes.
     *
     * @return void
     */
    public function bootstrap_with(array $bootstrappers);
    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function get_locale();
    /**
     * Get the application namespace.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function get_namespace();
    /**
     * Get the registered service provider instances if any exist.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @return array
     */
    public function get_providers($provider);
    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function has_been_bootstrapped();
    /**
     * Load and boot all of the remaining deferred providers.
     *
     * @return void
     */
    public function load_deferred_providers();
    /**
     * Set the current application locale.
     *
     * @param  string  $locale
     * @return void
     */
    public function set_locale($locale);
    /**
     * Determine if middleware has been disabled for the application.
     *
     * @return bool
     */
    public function should_skip_middleware();
    /**
     * Register a terminating callback with the application.
     *
     * @param  callable|string  $callback
     * @return \Illuminate\Contracts\Foundation\Application
     */
    public function terminating($callback);
    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate();
}