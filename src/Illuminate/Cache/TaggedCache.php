<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Cache\Events\Cache_Flushed;
use Illuminate\Cache\Events\Cache_Flushing;
use Illuminate\Contracts\Cache\Store;
class Tagged_Cache extends Repository
{
    use Retrieves_Multiple_Keys {
        putMany as putManyAlias;
    }
    /**
     * Create a new tagged cache instance.
     */
    public function __construct(
        Store $store,
        /**
         * The tag set instance.
         */
        protected \Illuminate\Cache\Tag_Set $tags
    )
    {
        parent::__construct($store);
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int|null  $ttl
     * @return bool
     */
    public function put_many(array $values, $ttl = null)
    {
        if ($ttl === null) {
            return $this->put_many_forever($values);
        }
        return $this->put_many_alias($values, $ttl);
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        return $this->store->increment($this->item_key($key), $value);
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->store->decrement($this->item_key($key), $value);
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        $this->event(new Cache_Flushing($this->get_name()));
        $this->tags->reset();
        $this->event(new Cache_Flushed($this->get_name()));
        return true;
    }
    /**
     * {@inheritdoc}
     */
    protected function item_key($key): string
    {
        return $this->tagged_item_key($key);
    }
    /**
     * Get a fully-qualified key for a tagged item.
     */
    public function tagged_item_key(string $key): string
    {
        return sha1($this->tags->get_namespace()) . ':' . $key;
    }
    /**
     * Fire an event for this cache instance.
     *
     * @param  object  $event
     * @return void
     */
    protected function event($event)
    {
        if (method_exists($event, 'setTags')) {
            $event->set_tags($this->tags->get_names());
        }
        parent::event($event);
    }
    /**
     * Get the tag set instance.
     */
    public function get_tags(): \Illuminate\Cache\Tag_Set
    {
        return $this->tags;
    }
}