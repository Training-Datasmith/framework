<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Carbon\Carbon_Interval;
use Illuminate\Cache\Dynamo_Db_Store;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Support\Interacts_With_Time;
class Cache_Command_Mutex implements Command_Mutex
{
    use Interacts_With_Time;
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
     * Create a new command mutex.
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }
    /**
     * Attempt to obtain a command mutex for the given command.
     *
     * @param  \Illuminate\Console\Command  $command
     * @return bool
     */
    public function create($command)
    {
        $store = $this->cache->store($this->store);
        $expires_at = method_exists($command, 'isolationLockExpiresAt') ? $command->isolation_lock_expires_at() : Carbon_Interval::hour();
        if ($this->should_use_locks($store->get_store())) {
            return $store->get_store()->lock($this->command_mutex_name($command), $this->seconds_until($expires_at))->get();
        }
        return $store->add($this->command_mutex_name($command), true, $expires_at);
    }
    /**
     * Determine if a command mutex exists for the given command.
     *
     * @param  \Illuminate\Console\Command  $command
     * @return bool
     */
    public function exists($command)
    {
        $store = $this->cache->store($this->store);
        if ($this->should_use_locks($store->get_store())) {
            $lock = $store->get_store()->lock($this->command_mutex_name($command));
            return tap(!$lock->get(), function ($exists) use ($lock): void {
                if ($exists) {
                    $lock->release();
                }
            });
        }
        return $this->cache->store($this->store)->has($this->command_mutex_name($command));
    }
    /**
     * Release the mutex for the given command.
     *
     * @param  \Illuminate\Console\Command  $command
     * @return bool
     */
    public function forget($command)
    {
        $store = $this->cache->store($this->store);
        if ($this->should_use_locks($store->get_store())) {
            return $store->get_store()->lock($this->command_mutex_name($command))->force_release();
        }
        return $this->cache->store($this->store)->forget($this->command_mutex_name($command));
    }
    /**
     * Get the isolatable command mutex name.
     *
     * @param  \Illuminate\Console\Command  $command
     */
    protected function command_mutex_name($command): string
    {
        $base_name = 'framework' . DIRECTORY_SEPARATOR . 'command-' . $command->get_name();
        return method_exists($command, 'isolatableId') ? $base_name . '-' . $command->isolatable_id() : $base_name;
    }
    /**
     * Specify the cache store that should be used.
     *
     * @param  string|null  $store
     * @return $this
     */
    public function use_store($store): static
    {
        $this->store = $store;
        return $this;
    }
    /**
     * Determine if the given store should use locks for command mutexes.
     *
     * @param  \Illuminate\Contracts\Cache\Store  $store
     */
    protected function should_use_locks($store): bool
    {
        return $store instanceof Lock_Provider && !$store instanceof Dynamo_Db_Store;
    }
}