<?php

declare(strict_types=1);

namespace Illuminate\Contracts\Support;

use ArrayAccess;
use IteratorAggregate;

interface ValidatedData extends Arrayable, ArrayAccess, IteratorAggregate
{
}
