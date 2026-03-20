<?php

declare (strict_types=1);
namespace Illuminate\Cache\Events;

class Retrieving_Many_Keys extends Cache_Event
{
    /**
     * The keys that are being retrieved.
     *
     * @var array
     */
    public $keys;
    /**
     * Create a new event instance.
     *
     * @param  string|null  $storeName
     * @param  array  $keys
     */
    public function __construct($store_name, $keys, array $tags = [])
    {
        parent::__construct($store_name, $keys[0] ?? '', $tags);
        $this->keys = $keys;
    }
}