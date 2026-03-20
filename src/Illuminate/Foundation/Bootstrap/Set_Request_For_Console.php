<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
class Set_Request_For_Console
{
    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app): void
    {
        $uri = $app->make('config')->get('app.url', 'http://localhost');
        $components = parse_url((string) $uri);
        $server = $_SERVER;
        if (isset($components['path'])) {
            $server = array_merge($server, ['SCRIPT_FILENAME' => $components['path'], 'SCRIPT_NAME' => $components['path']]);
        }
        $app->instance('request', Request::create($uri, 'GET', [], [], [], $server));
    }
}