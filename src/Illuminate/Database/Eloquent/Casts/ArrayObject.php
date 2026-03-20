<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Casts;

use ArrayObject as BaseArrayObject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
/**
 * @template TKey of array-key
 * @template TItem
 *
 * @extends  \ArrayObject<TKey, TItem>
 */
class ArrayObject extends Base_Array_Object implements Arrayable, JsonSerializable
{
    /**
     * Get a collection containing the underlying array.
     */
    public function collect(): \Illuminate\Support\Collection
    {
        return new Collection($this->get_array_copy());
    }
    /**
     * Get the instance as an array.
     */
    public function to_array(): array
    {
        return $this->get_array_copy();
    }
    /**
     * Get the array that should be JSON serialized.
     */
    public function jsonSerialize(): array
    {
        return $this->get_array_copy();
    }
}