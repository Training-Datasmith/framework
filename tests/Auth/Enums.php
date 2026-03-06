<?php

declare(strict_types=1);

namespace Illuminate\Tests\Auth;

enum AbilitiesEnum: string
{
    case VIEW_DASHBOARD = 'view-dashboard';
    case UPDATE = 'update';
}
