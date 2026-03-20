<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route_Collection;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'route:cache')]
class Route_Cache_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'route:cache';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a route cache file for faster route registration';
    /**
     * Create a new route command instance.
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->call_silent('route:clear');
        $routes = $this->get_fresh_application_routes();
        if (count($routes) === 0) {
            return $this->components->error("Your application doesn't have any routes.");
        }
        foreach ($routes as $route) {
            $route->prepare_for_serialization();
        }
        $this->files->put($this->laravel->get_cached_routes_path(), $this->build_route_cache_file($routes));
        $this->components->info('Routes cached successfully.');
    }
    /**
     * Boot a fresh copy of the application and get the routes.
     *
     * @return \Illuminate\Routing\RouteCollection
     */
    protected function get_fresh_application_routes()
    {
        return tap($this->get_fresh_application()['router']->get_routes(), function ($routes): void {
            $routes->refresh_name_lookups();
            $routes->refresh_action_lookups();
        });
    }
    /**
     * Get a fresh application instance.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    protected function get_fresh_application()
    {
        return tap(require $this->laravel->bootstrap_path('app.php'), function ($app): void {
            $app->make(Console_Kernel_Contract::class)->bootstrap();
        });
    }
    /**
     * Build the route cache file.
     */
    protected function build_route_cache_file(Route_Collection $routes): string
    {
        $stub = $this->files->get(__DIR__ . '/stubs/routes.stub');
        return str_replace('{{routes}}', var_export($routes->compile(), true), $stub);
    }
}