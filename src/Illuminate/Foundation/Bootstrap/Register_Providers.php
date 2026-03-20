<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Service_Provider;
class Register_Providers
{
    /**
     * The service providers that should be merged before registration.
     *
     * @var array
     */
    protected static $merge = [];
    /**
     * The path to the bootstrap provider configuration file.
     *
     * @var string|null
     */
    protected static $bootstrap_provider_path;
    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app): void
    {
        if (!$app->bound('config_loaded_from_cache') || $app->make('config_loaded_from_cache') === false) {
            $this->merge_additional_providers($app);
        }
        $app->register_configured_providers();
    }
    /**
     * Merge the additional configured providers into the configuration.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function merge_additional_providers(Application $app)
    {
        if (static::$bootstrap_provider_path && file_exists(static::$bootstrap_provider_path)) {
            $package_providers = require static::$bootstrap_provider_path;
            foreach ($package_providers as $index => $provider) {
                if (!class_exists($provider)) {
                    unset($package_providers[$index]);
                }
            }
        }
        $app->make('config')->set('app.providers', array_merge($app->make('config')->get('app.providers') ?? Service_Provider::default_providers()->to_array(), static::$merge, array_values($package_providers ?? [])));
    }
    /**
     * Merge the given providers into the provider configuration before registration.
     */
    public static function merge(array $providers, ?string $bootstrap_provider_path = null): void
    {
        static::$bootstrap_provider_path = $bootstrap_provider_path;
        static::$merge = array_values(array_filter(array_unique(array_merge(static::$merge, $providers))));
    }
    /**
     * Flush the bootstrapper's global state.
     */
    public static function flush_state(): void
    {
        static::$bootstrap_provider_path = null;
        static::$merge = [];
    }
}