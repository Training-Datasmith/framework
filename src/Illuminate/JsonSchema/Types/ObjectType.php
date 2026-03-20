<?php

declare (strict_types=1);
namespace Illuminate\Json_Schema\Types;

class Object_Type extends Type
{
    /**
     * Whether additional properties are allowed.
     */
    protected ?bool $additional_properties = null;
    /**
     * Create a new object type instance.
     *
     * @param  array<string, Type>  $properties
     */
    public function __construct(protected array $properties = [])
    {
    }
    /**
     * Disallow additional properties.
     */
    public function without_additional_properties(): static
    {
        $this->additional_properties = false;
        return $this;
    }
    /**
     * Set the type's default value.
     *
     * @param  array<string, mixed>  $value
     */
    public function default(array $value): static
    {
        $this->default = $value;
        return $this;
    }
}