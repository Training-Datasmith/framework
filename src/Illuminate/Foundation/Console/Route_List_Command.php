<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Routing\Url_Generator;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\View_Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use ReflectionClass;
use ReflectionFunction;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Terminal;
#[As_Command(name: 'route:list')]
class Route_List_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'route:list';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered routes';
    /**
     * The table headers for the command.
     *
     * @var string[]
     */
    protected $headers = ['Domain', 'Method', 'URI', 'Name', 'Action', 'Middleware'];
    /**
     * The terminal width resolver callback.
     *
     * @var \Closure|null
     */
    protected static $terminal_width_resolver;
    /**
     * The verb colors for the command.
     *
     * @var array
     */
    protected $verb_colors = ['ANY' => 'red', 'GET' => 'blue', 'HEAD' => '#6C7280', 'OPTIONS' => '#6C7280', 'POST' => 'yellow', 'PUT' => 'yellow', 'PATCH' => 'yellow', 'DELETE' => 'red'];
    /**
     * Create a new route command instance.
     */
    public function __construct(
        /**
         * The router instance.
         */
        protected \Illuminate\Routing\Router $router
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
        if (!$this->output->is_very_verbose()) {
            $this->router->flush_middleware_groups();
        }
        if (!$this->router->get_routes()->count()) {
            return $this->components->error("Your application doesn't have any routes.");
        }
        if (empty($routes = $this->get_routes())) {
            return $this->components->error("Your application doesn't have any routes matching the given criteria.");
        }
        $this->display_routes($routes);
    }
    /**
     * Compile the routes into a displayable format.
     */
    protected function get_routes(): array
    {
        $routes = (new Collection($this->router->get_routes()))->map(fn(\Illuminate\Routing\Route $route) => $this->get_route_information($route))->filter()->all();
        if (($sort = $this->option('sort')) !== null) {
            $routes = $this->sort_routes($sort, $routes);
        } else {
            $routes = $this->sort_routes('uri', $routes);
        }
        if ($this->option('reverse')) {
            $routes = array_reverse($routes);
        }
        return $this->pluck_columns($routes);
    }
    /**
     * Get the route information for a given route.
     *
     * @return array
     */
    protected function get_route_information(Route $route)
    {
        return $this->filter_route(['domain' => $route->domain(), 'method' => implode('|', $route->methods()), 'uri' => $route->uri(), 'name' => $route->get_name(), 'action' => ltrim($route->get_action_name(), '\\'), 'middleware' => $this->get_middleware($route), 'vendor' => $this->is_vendor_route($route)]);
    }
    /**
     * Sort the routes by a given element.
     *
     * @param  string  $sort
     * @return array
     */
    protected function sort_routes($sort, array $routes)
    {
        if ($sort === 'definition') {
            return $routes;
        }
        if (Str::contains($sort, ',')) {
            $sort = explode(',', $sort);
        }
        return (new Collection($routes))->sort_by($sort)->to_array();
    }
    /**
     * Remove unnecessary columns from the routes.
     */
    protected function pluck_columns(array $routes): array
    {
        return array_map(fn($route): array => Arr::only($route, $this->get_columns()), $routes);
    }
    /**
     * Display the route information on the console.
     *
     * @return void
     */
    protected function display_routes(array $routes)
    {
        $routes = new Collection($routes);
        $this->output->writeln($this->option('json') ? $this->as_json($routes) : $this->for_cli($routes));
    }
    /**
     * Get the middleware for the route.
     */
    protected function get_middleware(\Illuminate\Routing\Route $route): string
    {
        return (new Collection($this->router->gather_route_middleware($route)))->map(fn($middleware) => $middleware instanceof Closure ? 'Closure' : $middleware)->implode("\n");
    }
    /**
     * Determine if the route has been defined outside of the application.
     *
     * @return bool
     */
    protected function is_vendor_route(Route $route)
    {
        if ($route->action['uses'] instanceof Closure) {
            $path = (new ReflectionFunction($route->action['uses']))->get_file_name();
        } elseif (is_string($route->action['uses']) && str_contains($route->action['uses'], 'SerializableClosure')) {
            return false;
        } elseif (is_string($route->action['uses'])) {
            if ($this->is_framework_controller($route)) {
                return false;
            }
            $path = (new ReflectionClass($route->get_controller_class()))->get_file_name();
        } else {
            return false;
        }
        return str_starts_with($path, base_path('vendor'));
    }
    /**
     * Determine if the route uses a framework controller.
     */
    protected function is_framework_controller(Route $route): bool
    {
        return in_array($route->get_controller_class(), [\Illuminate\Routing\Redirect_Controller::class, \Illuminate\Routing\View_Controller::class], true);
    }
    /**
     * Filter the route by URI and / or name.
     *
     * @return array|null
     */
    protected function filter_route(array $route)
    {
        if ($this->option('name') && !Str::contains((string) $route['name'], $this->option('name')) || $this->option('action') && isset($route['action']) && is_string($route['action']) && !Str::contains($route['action'], $this->option('action')) || $this->option('path') && !Str::contains($route['uri'], $this->option('path')) || $this->option('method') && !Str::contains($route['method'], strtoupper($this->option('method'))) || $this->option('domain') && !Str::contains((string) $route['domain'], $this->option('domain')) || $this->option('middleware') && !Str::contains($route['middleware'], $this->option('middleware')) || $this->option('except-vendor') && $route['vendor'] || $this->option('only-vendor') && !$route['vendor']) {
            return;
        }
        if ($this->option('except-path')) {
            foreach (explode(',', $this->option('except-path')) as $path) {
                if (str_contains((string) $route['uri'], $path)) {
                    return;
                }
            }
        }
        return $route;
    }
    /**
     * Get the table headers for the visible columns.
     */
    protected function get_headers(): array
    {
        return Arr::only($this->headers, array_keys($this->get_columns()));
    }
    /**
     * Get the column names to show (lowercase table headers).
     */
    protected function get_columns(): array
    {
        return array_map(strtolower(...), $this->headers);
    }
    /**
     * Parse the column list.
     */
    protected function parse_columns(array $columns): array
    {
        $results = [];
        foreach ($columns as $column) {
            if (str_contains((string) $column, ',')) {
                $results = array_merge($results, explode(',', (string) $column));
            } else {
                $results[] = $column;
            }
        }
        return array_map(strtolower(...), $results);
    }
    /**
     * Convert the given routes to JSON.
     *
     * @param  \Illuminate\Support\Collection  $routes
     * @return string
     */
    protected function as_json($routes)
    {
        return $routes->map(function (array $route): array {
            $route['middleware'] = empty($route['middleware']) ? [] : explode("\n", (string) $route['middleware']);
            return $route;
        })->values()->to_json();
    }
    /**
     * Convert the given routes to regular CLI output.
     *
     * @param  \Illuminate\Support\Collection  $routes
     * @return array
     */
    protected function for_cli($routes)
    {
        $routes = $routes->map(fn($route): array => array_merge($route, ['action' => $this->format_action_for_cli($route), 'method' => $route['method'] == 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS' ? 'ANY' : $route['method'], 'uri' => $route['domain'] ? $route['domain'] . '/' . ltrim((string) $route['uri'], '/') : $route['uri']]));
        $max_method = mb_strlen((string) $routes->max('method'));
        $terminal_width = static::get_terminal_width();
        $route_count = $this->determine_route_count_output($routes, $terminal_width);
        return $routes->map(function ($route) use ($max_method, $terminal_width): array {
            ['action' => $action, 'domain' => $domain, 'method' => $method, 'middleware' => $middleware, 'uri' => $uri] = $route;
            $middleware = (new Stringable($middleware))->explode("\n")->filter()->when_not_empty(fn($collection): \Illuminate\Support\Collection => $collection->map(fn($middleware): string => sprintf('         %s⇂ %s', str_repeat(' ', $max_method), $middleware)))->implode("\n");
            $spaces = str_repeat(' ', max($max_method + 6 - mb_strlen($method), 0));
            $dots = str_repeat('.', max($terminal_width - mb_strlen($method . $spaces . $uri . $action) - 6 - ($action ? 1 : 0), 0));
            $dots = empty($dots) ? $dots : " {$dots}";
            if ($action && !$this->output->is_verbose() && mb_strlen($method . $spaces . $uri . $action . $dots) > $terminal_width - 6) {
                $action = substr($action, 0, $terminal_width - 7 - mb_strlen($method . $spaces . $uri . $dots)) . '…';
            }
            $method = (new Stringable($method))->explode('|')->map(fn($method): string => sprintf('<fg=%s>%s</>', $this->verb_colors[$method] ?? 'default', $method))->implode('<fg=#6C7280>|</>');
            return [sprintf('  <fg=white;options=bold>%s</> %s<fg=white>%s</><fg=#6C7280>%s %s</>', $method, $spaces, preg_replace('#({[^}]+})#', '<fg=yellow>$1</>', $uri), $dots, str_replace('   ', ' › ', $action ?? '')), $this->output->is_verbose() && !empty($middleware) ? "<fg=#6C7280>{$middleware}</>" : null];
        })->flatten()->filter()->prepend('')->push('')->push($route_count)->push('')->to_array();
    }
    /**
     * Get the formatted action for display on the CLI.
     *
     * @param  array  $route
     * @return string|null
     */
    protected function format_action_for_cli($route)
    {
        ['action' => $action, 'name' => $name] = $route;
        if ($action === 'Closure' || $action === View_Controller::class) {
            return $name;
        }
        $name = $name ? "{$name}   " : null;
        $root_controller_namespace = $this->laravel[Url_Generator::class]->get_root_controller_namespace() ?? $this->laravel->get_namespace() . 'Http\Controllers';
        if (str_starts_with((string) $action, (string) $root_controller_namespace)) {
            return $name . substr((string) $action, mb_strlen((string) $root_controller_namespace) + 1);
        }
        $action_class = explode('@', (string) $action)[0];
        if (class_exists($action_class) && str_starts_with((new ReflectionClass($action_class))->get_filename(), base_path('vendor'))) {
            $action_collection = new Collection(explode('\\', (string) $action));
            return $name . $action_collection->take(2)->implode('\\') . '   ' . $action_collection->last();
        }
        return $name . $action;
    }
    /**
     * Determine and return the output for displaying the number of routes in the CLI output.
     *
     * @param  \Illuminate\Support\Collection  $routes
     * @param  int  $terminalWidth
     */
    protected function determine_route_count_output($routes, $terminal_width): string
    {
        $route_count_text = 'Showing [' . $routes->count() . '] routes';
        $offset = $terminal_width - mb_strlen($route_count_text) - 2;
        $spaces = str_repeat(' ', $offset);
        return $spaces . '<fg=blue;options=bold>Showing [' . $routes->count() . '] routes</>';
    }
    /**
     * Get the terminal width.
     *
     * @return int
     */
    public static function get_terminal_width()
    {
        return is_null(static::$terminal_width_resolver) ? (new Terminal())->get_width() : call_user_func(static::$terminal_width_resolver);
    }
    /**
     * Set a callback that should be used when resolving the terminal width.
     *
     * @param  \Closure|null  $resolver
     */
    public static function resolve_terminal_width_using($resolver): void
    {
        static::$terminal_width_resolver = $resolver;
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['json', null, Input_Option::VALUE_NONE, 'Output the route list as JSON'], ['method', null, Input_Option::VALUE_OPTIONAL, 'Filter the routes by method'], ['action', null, Input_Option::VALUE_OPTIONAL, 'Filter the routes by action'], ['name', null, Input_Option::VALUE_OPTIONAL, 'Filter the routes by name'], ['domain', null, Input_Option::VALUE_OPTIONAL, 'Filter the routes by domain'], ['middleware', null, Input_Option::VALUE_OPTIONAL, 'Filter the routes by middleware'], ['path', null, Input_Option::VALUE_OPTIONAL, 'Only show routes matching the given path pattern'], ['except-path', null, Input_Option::VALUE_OPTIONAL, 'Do not display the routes matching the given path pattern'], ['reverse', 'r', Input_Option::VALUE_NONE, 'Reverse the ordering of the routes'], ['sort', null, Input_Option::VALUE_OPTIONAL, 'The column (domain, method, uri, name, action, middleware, definition) to sort by', 'uri'], ['except-vendor', null, Input_Option::VALUE_NONE, 'Do not display routes defined by vendor packages'], ['only-vendor', null, Input_Option::VALUE_NONE, 'Only display routes defined by vendor packages']];
    }
}