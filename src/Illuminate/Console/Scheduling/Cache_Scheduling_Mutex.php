<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use DateTimeInterface;
use Illuminate\Cache\Dynamo_Db_Store;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Cache\Lock_Provider;
class Cache_Scheduling_Mutex implements Scheduling_Mutex, Cache_Aware
{
    /**
     * The cache factory implementation.
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
     * Create a new scheduling strategy.
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }
    /**
     * Attempt to obtain a scheduling mutex for the given event.
     *
     * @return bool
     */
    public function create(Event $event, DateTimeInterface $time)
    {
        $mutex_name = $event->mutex_name() . $time->format('Hi');
        if ($this->should_use_locks($this->cache->store($this->store)->get_store())) {
            return $this->cache->store($this->store)->get_store()->lock($mutex_name, 3600)->acquire();
        }
        return $this->cache->store($this->store)->add($mutex_name, true, 3600);
    }
    /**
     * Determine if a scheduling mutex exists for the given event.
     *
     * @return bool
     */
    public function exists(Event $event, DateTimeInterface $time)
    {
        $mutex_name = $event->mutex_name() . $time->format('Hi');
        if ($this->should_use_locks($this->cache->store($this->store)->get_store())) {
            return !$this->cache->store($this->store)->get_store()->lock($mutex_name, 3600)->get(fn(): true => true);
        }
        return $this->cache->store($this->store)->has($mutex_name);
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