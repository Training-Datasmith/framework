<?php

namespace Illuminate\Cache\Events;

class WritingKey extends CacheEvent
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
     * The value that will be written.
     */
    public $value, /**
     * The number of seconds the key should be valid.
     */
    public $seconds = null, array $tags = [])
    {
        parent::__construct($storeName, $key, $tags);
    }
}
