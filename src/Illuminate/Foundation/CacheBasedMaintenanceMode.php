<?php

declare(strict_types=1);

namespace Illuminate\Foundation;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\MaintenanceMode;

class CacheBasedMaintenanceMode implements MaintenanceMode
{
    /**
     * Create a new cache based maintenance mode implementation.
     */
    public function __construct(
        /**
         * The cache factory.
         */
        protected \Illuminate\Contracts\Cache\Factory $cache,
        /**
         * The cache store that should be utilized.
         */
        protected string $store,
        /**
         * The cache key to use when storing maintenance mode information.
         */
        protected string $key
    ) {
    }

    /**
     * Take the application down for maintenance.
     */
    public function activate(array $payload): void
    {
        $this->getStore()->put($this->key, $payload);
    }

    /**
     * Take the application out of maintenance.
     */
    public function deactivate(): void
    {
        $this->getStore()->forget($this->key);
    }

    /**
     * Determine if the application is currently down for maintenance.
     */
    public function active(): bool
    {
        return $this->getStore()->has($this->key);
    }

    /**
     * Get the data array which was provided when the application was placed into maintenance.
     */
    public function data(): array
    {
        return $this->getStore()->get($this->key);
    }

    /**
     * Get the cache store to use.
     */
    protected function getStore(): Repository
    {
        return $this->cache->store($this->store);
    }
}
