<?php

declare (strict_types=1);
namespace Illuminate\Log;

use Illuminate\Support\Service_Provider;
class Log_Service_Provider extends Service_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('log', fn($app): \Illuminate\Log\Log_Manager => new Log_Manager($app));
    }
}