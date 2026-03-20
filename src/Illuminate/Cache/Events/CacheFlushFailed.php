<?php

declare (strict_types=1);
namespace Illuminate\Cache\Events;

class Cache_Flush_Failed
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
    public function __construct(
        /**
         * The name of the cache store.
         */
        public $store_name,
        array $tags = []
    )
    {
        $this->tags = $tags;
    }
    /**
     * Set the tags for the cache event.
     *
     * @param  array  $tags
     * @return $this
     */
    public function set_tags($tags): static
    {
        $this->tags = $tags;
        return $this;
    }
}