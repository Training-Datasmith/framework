<?php

declare (strict_types=1);
namespace Illuminate\Cache\Rate_Limiting;

class Global_Limit extends Limit
{
    /**
     * Create a new limit instance.
     */
    public function __construct(int $max_attempts, int $decay_seconds = 60)
    {
        parent::__construct('', $max_attempts, $decay_seconds);
    }
}