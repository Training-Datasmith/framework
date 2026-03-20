<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Alias_Loader;
use Illuminate\Foundation\Package_Manifest;
use Illuminate\Support\Facades\Facade;
class Register_Facades
{
    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app): void
    {
        Facade::clear_resolved_instances();
        Facade::set_facade_application($app);
        Alias_Loader::get_instance(array_merge($app->make('config')->get('app.aliases', []), $app->make(Package_Manifest::class)->aliases()))->register();
    }
}