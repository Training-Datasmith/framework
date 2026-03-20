<?php

declare (strict_types=1);
namespace Illuminate\Cache\Events;

class Writing_Key extends Cache_Event
{
    /**
     * Create a new event instance.
     *
     * @param  string|null  $storeName
     * @param  string  $key
     * @param  mixed  $value
     * @param  int|null  $seconds
     */
    public function __construct(
        $store_name,
        $key,
        /**
         * The value that will be written.
         */
        public $value,
        /**
         * The number of seconds the key should be valid.
         */
        public $seconds = null,
        array $tags = []
    )
    {
        parent::__construct($store_name, $key, $tags);
    }
}