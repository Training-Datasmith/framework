<?php

declare(strict_types=1);

namespace Illuminate\Tests\Testing\Fluent;

enum BackedEnum: string
{
    case test = 'test';
    case test_empty = '';
}
