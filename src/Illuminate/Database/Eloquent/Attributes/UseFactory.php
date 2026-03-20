<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_CLASS)]
class Use_Factory
{
    /**
     * Create a new attribute instance.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Factories\Factory>  $factoryClass
     */
    public function __construct(public string $factory_class)
    {
    }
}