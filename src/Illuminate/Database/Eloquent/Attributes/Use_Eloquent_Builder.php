<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_CLASS)]
class Use_Eloquent_Builder
{
    /**
     * Create a new attribute instance.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Builder>  $builderClass
     */
    public function __construct(public string $builder_class)
    {
    }
}