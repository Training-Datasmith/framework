<?php

declare (strict_types=1);
namespace Illuminate\Cache\Events;

class Writing_Many_Keys extends Cache_Event
{
    /**
     * The keys that are being written.
     *
     * @var mixed
     */
    public $keys;
    /**
     * Create a new event instance.
     *
     * @param  string|null  $storeName
     * @param  array  $keys
     * @param  array  $values
     * @param  int|null  $seconds
     */
    public function __construct(
        $store_name,
        $keys,
        /**
         * The value that is being written.
         */
        public $values,
        /**
         * The number of seconds the keys should be valid.
         */
        public $seconds = null,
        array $tags = []
    )
    {
        parent::__construct($store_name, $keys[0], $tags);
        $this->keys = $keys;
    }
}