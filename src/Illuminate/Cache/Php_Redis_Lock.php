<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Redis\Connections\Php_Redis_Connection;
class Php_Redis_Lock extends Redis_Lock
{
    /**
     * Create a new phpredis lock instance.
     */
    public function __construct(Php_Redis_Connection $redis, string $name, int $seconds, ?string $owner = null)
    {
        parent::__construct($redis, $name, $seconds, $owner);
    }
    /**
     * {@inheritDoc}
     */
    public function release(): bool
    {
        return (bool) $this->redis->eval(Lua_Scripts::release_lock(), 1, $this->name, ...$this->redis->pack([$this->owner]));
    }
}