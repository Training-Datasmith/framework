<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_CLASS)]
class Collected_By
{
    /**
     * Create a new attribute instance.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Collection<*, *>>  $collectionClass
     */
    public function __construct(public string $collection_class)
    {
    }
}