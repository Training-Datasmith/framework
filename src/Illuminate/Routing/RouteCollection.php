<?php

namespace Illuminate\Routing;

use Illuminate\Container\Container;
use Illuminate\Http\Request;

class RouteCollection extends AbstractRouteCollection
{
    /**
     * An array of the routes keyed by method.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * A flattened array of all of the routes.
     *
     * @var \Illuminate\Routing\Route[]
     */
    protected $allRoutes = [];

    /**
     * A look-up table of routes by their names.
     *
     * @var \Illuminate\Routing\Route[]
     */
    protected $nameList = [];

    /**
     * A look-up table of routes by controller action.
     *
     * @var \Illuminate\Routing\Route[]
     */
    protected $actionList = [];

    /**
     * Add a Route instance to the collection.
     */
    public function add(Route $route): Route
    {
        $this->addToCollections($route);

        $this->addLookups($route);

        return $route;
    }

    /**
     * Add the given route to the arrays of routes.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return void
     */
    protected function addToCollections($route)
    {
        $methods = $route->methods();
        $domainAndUri = $route->getDomain().$route->uri();

        foreach ($methods as $method) {
            $this->routes[$method][$domainAndUri] = $route;
        }

        $this->allRoutes[implode('|', $methods).$domainAndUri] = $route;
    }

    /**
     * Add the route to any look-up tables if necessary.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return void
     */
    protected function addLookups($route)
    {
        // If the route has a name, we will add it to the name look-up table, so that we
        // will quickly be able to find the route associated with a name and not have
        // to iterate through every route every time we need to find a named route.
        if (($name = $route->getName()) && ! $this->inNameLookup($name)) {
            $this->nameList[$name] = $route;
        }

        // When the route is routing to a controller we will also store the action that
        // is used by the route. This will let us reverse route to controllers while
        // processing a request and easily generate URLs to the given controllers.
        $action = $route->getAction();

        if (($controller = $action['controller'] ?? null) && ! $this->inActionLookup($controller)) {
            $this->addToActionList($action, $route);
        }
    }

    /**
     * Add a route to the controller action dictionary.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return void
     */
    protected function addToActionList(array $action, $route)
    {
        $this->actionList[trim((string) $action['controller'], '\\')] = $route;
    }

    /**
     * Determine if the given controller is in the action lookup table.
     *
     * @param  string  $controller
     */
    protected function inActionLookup($controller): bool
    {
        return array_key_exists($controller, $this->actionList);
    }

    /**
     * Determine if the given name is in the name lookup table.
     *
     * @param  string  $name
     */
    protected function inNameLookup($name): bool
    {
        return array_key_exists($name, $this->nameList);
    }

    /**
     * Refresh the name look-up table.
     *
     * This is done in case any names are fluently defined or if routes are overwritten.
     */
    public function refreshNameLookups(): void
    {
        $this->nameList = [];

        foreach ($this->allRoutes as $route) {
            if (($name = $route->getName()) && ! $this->inNameLookup($name)) {
                $this->nameList[$name] = $route;
            }
        }
    }

    /**
     * Refresh the action look-up table.
     *
     * This is done in case any actions are overwritten with new controllers.
     */
    public function refreshActionLookups(): void
    {
        $this->actionList = [];

        foreach ($this->allRoutes as $route) {
            if (($controller = $route->getAction()['controller'] ?? null) && ! $this->inActionLookup($controller)) {
                $this->addToActionList($route->getAction(), $route);
            }
        }
    }

    /**
     * Find the first route matching a given request.
     *
     * @return \Illuminate\Routing\Route
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function match(Request $request)
    {
        $routes = $this->get($request->getMethod());

        // First, we will see if we can find a matching route for this current request
        // method. If we can, great, we can just return it so that it can be called
        // by the consumer. Otherwise we will check for routes with another verb.
        $route = $this->matchAgainstRoutes($routes, $request);

        return $this->handleMatchedRoute($request, $route);
    }

    /**
     * Get routes from the collection by method.
     *
     * @param  string|null  $method
     * @return \Illuminate\Routing\Route[]
     */
    public function get($method = null)
    {
        return is_null($method) ? $this->getRoutes() : ($this->routes[$method] ?? []);
    }

    /**
     * Determine if the route collection contains a given named route.
     *
     * @param  string  $name
     */
    public function hasNamedRoute($name): bool
    {
        return ! is_null($this->getByName($name));
    }

    /**
     * Get a route instance by its name.
     *
     * @param  string  $name
     * @return \Illuminate\Routing\Route|null
     */
    public function getByName($name)
    {
        return $this->nameList[$name] ?? null;
    }

    /**
     * Get a route instance by its controller action.
     *
     * @param  string  $action
     * @return \Illuminate\Routing\Route|null
     */
    public function getByAction($action)
    {
        return $this->actionList[$action] ?? null;
    }

    /**
     * Get all of the routes in the collection.
     *
     * @return \Illuminate\Routing\Route[]
     */
    public function getRoutes(): array
    {
        return array_values($this->allRoutes);
    }

    /**
     * Get all of the routes keyed by their HTTP verb / method.
     *
     * @return array
     */
    public function getRoutesByMethod()
    {
        return $this->routes;
    }

    /**
     * Get all of the routes keyed by their name.
     *
     * @return \Illuminate\Routing\Route[]
     */
    public function getRoutesByName()
    {
        return $this->nameList;
    }

    /**
     * Convert the collection to a Symfony RouteCollection instance.
     *
     * @return \Symfony\Component\Routing\RouteCollection
     */
    public function toSymfonyRouteCollection()
    {
        $symfonyRoutes = parent::toSymfonyRouteCollection();

        $this->refreshNameLookups();

        return $symfonyRoutes;
    }

    /**
     * Convert the collection to a CompiledRouteCollection instance.
     */
    public function toCompiledRouteCollection(Router $router, Container $container): \Illuminate\Routing\CompiledRouteCollection
    {
        ['compiled' => $compiled, 'attributes' => $attributes] = $this->compile();

        return (new CompiledRouteCollection($compiled, $attributes))
            ->setRouter($router)
            ->setContainer($container);
    }
}
