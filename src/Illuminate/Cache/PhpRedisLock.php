<?php

declare(strict_types=1);

namespace Illuminate\Cache;

use Illuminate\Redis\Connections\PhpRedisConnection;

class PhpRedisLock extends RedisLock
{
    /**
     * Create a new phpredis lock instance.
     */
    public function __construct(PhpRedisConnection $redis, string $name, int $seconds, ?string $owner = null)
    {
        parent::__construct($redis, $name, $seconds, $owner);
    }

    /**
     * {@inheritDoc}
     */
    public function release(): bool
    {
        return (bool) $this->redis->eval(
            LuaScripts::releaseLock(),
            1,
            $this->name,
            ...$this->redis->pack([$this->owner])
        );
    }
}
