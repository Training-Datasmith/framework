<?php

declare(strict_types=1);

namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Store;

class TagSet
{
    /**
     * Create a new TagSet instance.
     */
    public function __construct(
        /**
         * The cache store implementation.
         */
        protected \Illuminate\Contracts\Cache\Store $store,
        /**
         * The tag names.
         */
        protected array $names = []
    ) {
    }

    /**
     * Reset all tags in the set.
     */
    public function reset(): void
    {
        array_walk($this->names, $this->resetTag(...));
    }

    /**
     * Reset the tag and return the new tag identifier.
     *
     * @return string
     */
    public function resetTag(string $name): string|array
    {
        $this->store->forever($this->tagKey($name), $id = str_replace('.', '', uniqid('', true)));

        return $id;
    }

    /**
     * Flush all the tags in the set.
     */
    public function flush(): void
    {
        array_walk($this->names, $this->flushTag(...));
    }

    /**
     * Flush the tag from the cache.
     */
    public function flushTag(string $name): void
    {
        $this->store->forget($this->tagKey($name));
    }

    /**
     * Get a unique namespace that changes when any of the tags are flushed.
     */
    public function getNamespace(): string
    {
        return implode('|', $this->tagIds());
    }

    /**
     * Get an array of tag identifiers for all of the tags in the set.
     */
    protected function tagIds(): array
    {
        return array_map($this->tagId(...), $this->names);
    }

    /**
     * Get the unique tag identifier for a given tag.
     *
     * @param  string  $name
     * @return string
     */
    public function tagId($name)
    {
        return $this->store->get($this->tagKey($name)) ?: $this->resetTag($name);
    }

    /**
     * Get the tag identifier key for a given tag.
     */
    public function tagKey(string $name): string
    {
        return 'tag:'.$name.':key';
    }

    /**
     * Get all of the tag names in the set.
     */
    public function getNames(): array
    {
        return $this->names;
    }
}
