<?php

declare(strict_types=1);

namespace Illuminate\Tests\JsonSchema\Fixtures\Enums;

enum StringBackedEnum: string
{
    case One = 'one';
    case Two = 'two';
}
