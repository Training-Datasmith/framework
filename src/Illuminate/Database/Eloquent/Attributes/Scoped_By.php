<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Scoped_By
{
    /**
     * Create a new attribute instance.
     */
    public function __construct(public array|string $classes)
    {
    }
}