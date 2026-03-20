<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_CLASS)]
class Use_Policy
{
    /**
     * Create a new attribute instance.
     *
     * @param  class-string<*>  $class
     */
    public function __construct(public string $class)
    {
    }
}