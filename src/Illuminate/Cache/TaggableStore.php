<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Store;
abstract class Taggable_Store implements Store
{
    /**
     * Begin executing a new tags operation.
     *
     * @param  mixed  $names
     * @return \Illuminate\Cache\TaggedCache
     */
    public function tags($names)
    {
        return new Tagged_Cache($this, new Tag_Set($this, is_array($names) ? $names : func_get_args()));
    }
}