<?php

namespace Illuminate\Cache\Events;

class CacheFlushed
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
     */
    public function __construct(/**
     * The name of the cache store.
     */
    public $storeName, array $tags = [])
    {
        $this->tags = $tags;
    }

    /**
     * Set the tags for the cache event.
     *
     * @param  array  $tags
     * @return $this
     */
    public function setTags($tags): static
    {
        $this->tags = $tags;

        return $this;
    }
}
