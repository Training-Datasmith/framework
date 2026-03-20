<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Cache\Dynamo_Db_Store;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Cache\Lock_Provider;
class Cache_Event_Mutex implements Event_Mutex, Cache_Aware
{
    /**
     * The cache repository implementation.
     *
     * @var \Illuminate\Contracts\Cache\Factory
     */
    public $cache;
    /**
     * The cache store that should be used.
     *
     * @var string|null
     */
    public $store;
    /**
     * Create a new overlapping strategy.
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }
    /**
     * Attempt to obtain an event mutex for the given event.
     *
     * @return bool
     */
    public function create(Event $event)
    {
        if ($this->should_use_locks($this->cache->store($this->store)->get_store())) {
            return $this->cache->store($this->store)->get_store()->lock($event->mutex_name(), $event->expires_at * 60)->acquire();
        }
        return $this->cache->store($this->store)->add($event->mutex_name(), true, $event->expires_at * 60);
    }
    /**
     * Determine if an event mutex exists for the given event.
     *
     * @return bool
     */
    public function exists(Event $event)
    {
        if ($this->should_use_locks($this->cache->store($this->store)->get_store())) {
            return !$this->cache->store($this->store)->get_store()->lock($event->mutex_name(), $event->expires_at * 60)->get(fn(): true => true);
        }
        return $this->cache->store($this->store)->has($event->mutex_name());
    }
    /**
     * Clear the event mutex for the given event.
     */
    public function forget(Event $event): void
    {
        if ($this->should_use_locks($this->cache->store($this->store)->get_store())) {
            $this->cache->store($this->store)->get_store()->lock($event->mutex_name(), $event->expires_at * 60)->force_release();
            return;
        }
        $this->cache->store($this->store)->forget($event->mutex_name());
    }
    /**
     * Determine if the given store should use locks for cache event mutexes.
     *
     * @param  \Illuminate\Contracts\Cache\Store  $store
     */
    protected function should_use_locks($store): bool
    {
        return $store instanceof Lock_Provider && !$store instanceof Dynamo_Db_Store;
    }
    /**
     * Specify the cache store that should be used.
     *
     * @param  string  $store
     * @return $this
     */
    public function use_store($store): static
    {
        $this->store = $store;
        return $this;
    }
}