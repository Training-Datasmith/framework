<?php

declare(strict_types=1);

namespace Illuminate\Cache\Events;

class KeyWriteFailed extends CacheEvent
{
    /**
     * Create a new event instance.
     *
     * @param  string|null  $storeName
     * @param  string  $key
     * @param  mixed  $value
     * @param  int|null  $seconds
     */
    public function __construct($storeName, $key, /**
     * The value that would have been written.
     */
        public $value, /**
     * The number of seconds the key should have been valid.
     */
        public $seconds = null, array $tags = [])
    {
        parent::__construct($storeName, $key, $tags);
    }
}
