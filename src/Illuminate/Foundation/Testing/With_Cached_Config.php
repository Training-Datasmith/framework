<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\Load_Configuration;
trait With_Cached_Config
{
    /**
     * After resolving the configuration once, we can cache it for the remaining tests.
     */
    protected function set_up_with_cached_config(): void
    {
        if ((Cached_State::$cached_config ?? null) === null) {
            Cached_State::$cached_config = $this->app->make('config')->all();
        }
        $this->mark_config_cached($this->app);
    }
    /**
     * Reset the cached configuration.
     *
     * This is helpful if some of the tests in the suite apply this trait while others do not.
     */
    protected function tear_down_with_cached_config(): void
    {
        Load_Configuration::always_use(null);
    }
    /**
     * Inform the container that the configuration is cached.
     */
    protected function mark_config_cached(Application $app): void
    {
        $app->instance('config_loaded_from_cache', true);
        Load_Configuration::always_use(static fn(): ?array => Cached_State::$cached_config);
    }
}