<?php

declare (strict_types=1);
namespace Illuminate\Cache\Events;

class Cache_Hit extends Cache_Event
{
    /**
     * Create a new event instance.
     *
     * @param  string|null  $storeName
     * @param  string  $key
     * @param  mixed  $value
     */
    public function __construct(
        $store_name,
        $key,
        /**
         * The value that was retrieved.
         */
        public $value,
        array $tags = []
    )
    {
        parent::__construct($store_name, $key, $tags);
    }
}