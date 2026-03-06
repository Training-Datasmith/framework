<?php

declare(strict_types=1);

namespace Illuminate\Tests\Support\Fixtures;

enum IntBackedEnum: int
{
    case ROLE_ADMIN = 1;
    case TWO = 2;
}
