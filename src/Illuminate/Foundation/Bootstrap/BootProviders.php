<?php

namespace Illuminate\Foundation\Bootstrap;

use Illuminate\Contracts\Foundation\Application;

class BootProviders
{
    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app): void
    {
        $app->boot();
    }
}
