<?php

declare (strict_types=1);
namespace Illuminate\Json_Schema\Types;

class Array_Type extends Type
{
    /**
     * The minimum number of items (inclusive).
     */
    protected ?int $min_items = null;
    /**
     * The maximum number of items (inclusive).
     */
    protected ?int $max_items = null;
    /**
     * The schema of the items contained in the array.
     */
    protected ?Type $items = null;
    /**
     * Whether the array items must be unique.
     */
    protected ?bool $unique_items = null;
    /**
     * Set the minimum number of items (inclusive).
     */
    public function min(int $value): static
    {
        $this->min_items = $value;
        return $this;
    }
    /**
     * Set the maximum number of items (inclusive).
     */
    public function max(int $value): static
    {
        $this->max_items = $value;
        return $this;
    }
    /**
     * Set the schema for array items.
     */
    public function items(Type $type): static
    {
        $this->items = $type;
        return $this;
    }
    /**
     * Indicate that the array items must be unique.
     */
    public function unique(bool $unique = true): static
    {
        if ($unique) {
            $this->unique_items = true;
        }
        return $this;
    }
    /**
     * Set the type's default value.
     *
     * @param  array<int, mixed>  $value
     */
    public function default(array $value): static
    {
        $this->default = $value;
        return $this;
    }
}