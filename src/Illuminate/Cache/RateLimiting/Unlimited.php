<?php

declare (strict_types=1);
namespace Illuminate\Cache\Rate_Limiting;

class Unlimited extends Global_Limit
{
    /**
     * Create a new limit instance.
     */
    public function __construct()
    {
        parent::__construct(PHP_INT_MAX);
    }
}