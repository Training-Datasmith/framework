<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Routing;

enum AbilityBackedEnum: string
{
    case AccessRoute = 'access-route';
    case NotAccessRoute = 'not-access-route';
}
