<?php

declare(strict_types=1);

namespace Illuminate\Session;

use SessionHandlerInterface;

class CacheBasedSessionHandler implements SessionHandlerInterface
{
    /**
     * Create a new cache driven handler instance.
     *
     * @param  int  $minutes
     */
    public function __construct(
        /**
         * The cache repository instance.
         */
        protected \Illuminate\Contracts\Cache\Repository $cache,
        /**
         * The number of minutes to store the data in the cache.
         */
        protected $minutes
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId): string
    {
        return $this->cache->get($sessionId, '');
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data): bool
    {
        return $this->cache->put($sessionId, $data, $this->minutes * 60);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId): bool
    {
        return $this->cache->forget($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime): int
    {
        return 0;
    }

    /**
     * Get the underlying cache repository.
     */
    public function getCache(): \Illuminate\Contracts\Cache\Repository
    {
        return $this->cache;
    }
}
