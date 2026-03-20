<?php

declare (strict_types=1);
namespace Illuminate\Json_Schema\Types;

class Boolean_Type extends Type
{
    /**
     * Set the type's default value.
     */
    public function default(bool $value): static
    {
        $this->default = $value;
        return $this;
    }
}