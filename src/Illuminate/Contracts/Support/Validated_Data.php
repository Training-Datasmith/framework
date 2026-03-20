<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Support;

use ArrayAccess;
use IteratorAggregate;
interface Validated_Data extends Arrayable, ArrayAccess, IteratorAggregate
{
}