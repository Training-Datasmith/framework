<?php

declare (strict_types=1);
namespace Illuminate\Console\Events;

use Illuminate\Console\Application;
class Artisan_Starting
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Console\Application  $artisan  The Artisan application instance.
     */
    public function __construct(public Application $artisan)
    {
    }
}