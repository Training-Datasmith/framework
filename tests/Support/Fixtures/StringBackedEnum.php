<?php

declare(strict_types=1);

namespace Illuminate\Tests\Support\Fixtures;

enum StringBackedEnum: string
{
    case ADMIN_LABEL = 'I am \'admin\'';
    case HELLO_WORLD = 'Hello world';
}
