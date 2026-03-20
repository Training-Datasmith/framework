<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Store;
class Tag_Set
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
    )
    {
    }
    /**
     * Reset all tags in the set.
     */
    public function reset(): void
    {
        array_walk($this->names, $this->reset_tag(...));
    }
    /**
     * Reset the tag and return the new tag identifier.
     *
     * @return string
     */
    public function reset_tag(string $name): string|array
    {
        $this->store->forever($this->tag_key($name), $id = str_replace('.', '', uniqid('', true)));
        return $id;
    }
    /**
     * Flush all the tags in the set.
     */
    public function flush(): void
    {
        array_walk($this->names, $this->flush_tag(...));
    }
    /**
     * Flush the tag from the cache.
     */
    public function flush_tag(string $name): void
    {
        $this->store->forget($this->tag_key($name));
    }
    /**
     * Get a unique namespace that changes when any of the tags are flushed.
     */
    public function get_namespace(): string
    {
        return implode('|', $this->tag_ids());
    }
    /**
     * Get an array of tag identifiers for all of the tags in the set.
     */
    protected function tag_ids(): array
    {
        return array_map($this->tag_id(...), $this->names);
    }
    /**
     * Get the unique tag identifier for a given tag.
     *
     * @param  string  $name
     * @return string
     */
    public function tag_id($name)
    {
        return $this->store->get($this->tag_key($name)) ?: $this->reset_tag($name);
    }
    /**
     * Get the tag identifier key for a given tag.
     */
    public function tag_key(string $name): string
    {
        return 'tag:' . $name . ':key';
    }
    /**
     * Get all of the tag names in the set.
     */
    public function get_names(): array
    {
        return $this->names;
    }
}