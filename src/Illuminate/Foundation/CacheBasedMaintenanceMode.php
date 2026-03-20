<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Maintenance_Mode;
class Cache_Based_Maintenance_Mode implements Maintenance_Mode
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
    )
    {
    }
    /**
     * Take the application down for maintenance.
     */
    public function activate(array $payload): void
    {
        $this->get_store()->put($this->key, $payload);
    }
    /**
     * Take the application out of maintenance.
     */
    public function deactivate(): void
    {
        $this->get_store()->forget($this->key);
    }
    /**
     * Determine if the application is currently down for maintenance.
     */
    public function active(): bool
    {
        return $this->get_store()->has($this->key);
    }
    /**
     * Get the data array which was provided when the application was placed into maintenance.
     */
    public function data(): array
    {
        return $this->get_store()->get($this->key);
    }
    /**
     * Get the cache store to use.
     */
    protected function get_store(): Repository
    {
        return $this->cache->store($this->store);
    }
}