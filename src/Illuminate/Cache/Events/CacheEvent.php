<?php

declare(strict_types=1);

namespace Illuminate\Cache\Events;

abstract class CacheEvent
{
    /**
     * The tags that were assigned to the key.
     *
     * @var array
     */
    public $tags;

    /**
     * Create a new event instance.
     *
     * @param  string|null  $storeName
     * @param  string  $key
     */
    public function __construct(/**
     * The name of the cache store.
     */
        public $storeName, /**
     * The key of the event.
     */
        public $key,
        array $tags = []
    ) {
        $this->tags = $tags;
    }

    /**
     * Set the tags for the cache event.
     *
     * @param  array  $tags
     * @return $this
     */
    public function setTags($tags)
    {
        $this->tags = $tags;

        return $this;
    }
}
