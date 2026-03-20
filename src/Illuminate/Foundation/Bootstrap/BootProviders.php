<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
class Boot_Providers
{
    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app): void
    {
        $app->boot();
    }
}