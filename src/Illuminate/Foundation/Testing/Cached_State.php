<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

class Cached_State
{
    public static ?array $cached_routes = null;
    public static ?array $cached_config = null;
}