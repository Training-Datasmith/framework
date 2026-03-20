<?php

declare (strict_types=1);
namespace Illuminate\Json_Schema\Types;

class Number_Type extends Type
{
    /**
     * The minimum value (inclusive).
     */
    protected int|float|null $minimum = null;
    /**
     * The maximum value (inclusive).
     */
    protected int|float|null $maximum = null;
    /**
     * The number the value must be a multiple of.
     */
    protected int|float|null $multiple_of = null;
    /**
     * Set the minimum value (inclusive).
     */
    public function min(int|float $value): static
    {
        $this->minimum = $value;
        return $this;
    }
    /**
     * Set the maximum value (inclusive).
     */
    public function max(int|float $value): static
    {
        $this->maximum = $value;
        return $this;
    }
    /**
     * Set the number the value must be a multiple of.
     */
    public function multiple_of(int|float $value): static
    {
        $this->multiple_of = $value;
        return $this;
    }
    /**
     * Set the type's default value.
     */
    public function default(int|float $value): static
    {
        $this->default = $value;
        return $this;
    }
}