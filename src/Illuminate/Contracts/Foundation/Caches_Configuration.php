<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Foundation;

interface Caches_Configuration
{
    /**
     * Determine if the application configuration is cached.
     *
     * @return bool
     */
    public function configuration_is_cached();
    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function get_cached_config_path();
    /**
     * Get the path to the cached services.php file.
     *
     * @return string
     */
    public function get_cached_services_path();
}