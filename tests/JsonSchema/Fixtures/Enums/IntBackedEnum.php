<?php

declare(strict_types=1);

namespace Illuminate\Tests\JsonSchema\Fixtures\Enums;

enum IntBackedEnum: int
{
    case One = 1;
    case Two = 2;
}
