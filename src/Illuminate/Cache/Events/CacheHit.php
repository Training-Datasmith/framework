<?php

declare(strict_types=1);

namespace Illuminate\Cache\Events;

class CacheHit extends CacheEvent
{
    /**
     * Create a new event instance.
     *
     * @param  string|null  $storeName
     * @param  string  $key
     * @param  mixed  $value
     */
    public function __construct($storeName, $key, /**
     * The value that was retrieved.
     */
        public $value, array $tags = [])
    {
        parent::__construct($storeName, $key, $tags);
    }
}
