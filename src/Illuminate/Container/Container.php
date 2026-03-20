<?php

declare (strict_types=1);
namespace Illuminate\Container;

use ArrayAccess;
use Closure;
use Exception;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Container\Binding_Resolution_Exception;
use Illuminate\Contracts\Container\Circular_Dependency_Exception;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Container\Contextual_Attribute;
use Illuminate\Contracts\Container\Self_Building;
use Illuminate\Support\Traits\Reflects_Closures;
use LogicException;
use Reflection_Attribute;
use ReflectionClass;
use Reflection_Exception;
use ReflectionFunction;
use ReflectionParameter;
use TypeError;
class Container implements ArrayAccess, Container_Contract
{
    use Reflects_Closures;
    /**
     * The current globally available container (if any).
     *
     * @var static
     */
    protected static $instance;
    /**
     * An array of the types that have been resolved.
     *
     * @var bool[]
     */
    protected $resolved = [];
    /**
     * The container's bindings.
     *
     * @var array[]
     */
    protected $bindings = [];
    /**
     * The container's method bindings.
     *
     * @var \Closure[]
     */
    protected $method_bindings = [];
    /**
     * The container's shared instances.
     *
     * @var object[]
     */
    protected $instances = [];
    /**
     * The container's scoped instances.
     *
     * @var array
     */
    protected $scoped_instances = [];
    /**
     * The registered type aliases.
     *
     * @var string[]
     */
    protected $aliases = [];
    /**
     * The registered aliases keyed by the abstract name.
     *
     * @var array[]
     */
    protected $abstract_aliases = [];
    /**
     * The extension closures for services.
     *
     * @var array[]
     */
    protected $extenders = [];
    /**
     * All of the registered tags.
     *
     * @var array[]
     */
    protected $tags = [];
    /**
     * The stack of concretions currently being built.
     *
     * @var array[]
     */
    protected $build_stack = [];
    /**
     * The parameter override stack.
     *
     * @var array[]
     */
    protected $with = [];
    /**
     * The contextual binding map.
     *
     * @var array[]
     */
    public $contextual = [];
    /**
     * The contextual attribute handlers.
     *
     * @var array[]
     */
    public $contextual_attributes = [];
    /**
     * Whether an abstract class has already had its attributes checked for bindings.
     *
     * @var array<class-string, true>
     */
    protected $checked_for_attribute_bindings = [];
    /**
     * Whether a class has already been checked for Singleton or Scoped attributes.
     *
     * @var array<class-string, "scoped"|"singleton"|null>
     */
    protected $checked_for_singleton_or_scoped_attributes = [];
    /**
     * All of the registered rebound callbacks.
     *
     * @var array[]
     */
    protected $rebound_callbacks = [];
    /**
     * All of the global before resolving callbacks.
     *
     * @var \Closure[]
     */
    protected $global_before_resolving_callbacks = [];
    /**
     * All of the global resolving callbacks.
     *
     * @var \Closure[]
     */
    protected $global_resolving_callbacks = [];
    /**
     * All of the global after resolving callbacks.
     *
     * @var \Closure[]
     */
    protected $global_after_resolving_callbacks = [];
    /**
     * All of the before resolving callbacks by class type.
     *
     * @var array[]
     */
    protected $before_resolving_callbacks = [];
    /**
     * All of the resolving callbacks by class type.
     *
     * @var array[]
     */
    protected $resolving_callbacks = [];
    /**
     * All of the after resolving callbacks by class type.
     *
     * @var array[]
     */
    protected $after_resolving_callbacks = [];
    /**
     * All of the after resolving attribute callbacks by class type.
     *
     * @var array[]
     */
    protected $after_resolving_attribute_callbacks = [];
    /**
     * The callback used to determine the container's environment.
     *
     * @var (callable(array<int, string>|string): bool|string)|null
     */
    protected $environment_resolver;
    /**
     * Define a contextual binding.
     *
     * @param  array|string  $concrete
     * @return \Illuminate\Contracts\Container\ContextualBindingBuilder
     */
    public function when($concrete): \Illuminate\Container\Contextual_Binding_Builder
    {
        $aliases = [];
        foreach (Util::array_wrap($concrete) as $c) {
            $aliases[] = $this->get_alias($c);
        }
        return new Contextual_Binding_Builder($this, $aliases);
    }
    /**
     * Define a contextual binding based on an attribute.
     */
    public function when_has_attribute(string $attribute, Closure $handler): void
    {
        $this->contextual_attributes[$attribute] = $handler;
    }
    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string  $abstract
     */
    public function bound($abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || $this->is_alias($abstract);
    }
    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }
    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param  string  $abstract
     */
    public function resolved($abstract): bool
    {
        if ($this->is_alias($abstract)) {
            $abstract = $this->get_alias($abstract);
        }
        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }
    /**
     * Determine if a given type is shared.
     *
     * @param  string  $abstract
     */
    public function is_shared($abstract): bool
    {
        if (isset($this->instances[$abstract])) {
            return true;
        }
        if (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true) {
            return true;
        }
        if (!class_exists($abstract)) {
            return false;
        }
        if (($scoped_type = $this->get_scoped_typed($abstract)) === null) {
            return false;
        }
        if ($scoped_type === 'scoped') {
            if (!in_array($abstract, $this->scoped_instances, true)) {
                $this->scoped_instances[] = $abstract;
            }
        }
        return true;
    }
    /**
     * Determine if a ReflectionClass has scoping attributes applied.
     *
     * @param  ReflectionClass<object>|class-string  $reflection
     * @return "singleton"|"scoped"|null
     */
    protected function get_scoped_typed(ReflectionClass|string $reflection): ?string
    {
        $class_name = $reflection instanceof ReflectionClass ? $reflection->get_name() : $reflection;
        if (array_key_exists($class_name, $this->checked_for_singleton_or_scoped_attributes)) {
            return $this->checked_for_singleton_or_scoped_attributes[$class_name];
        }
        try {
            $reflection = $reflection instanceof ReflectionClass ? $reflection : new ReflectionClass($reflection);
        } catch (Reflection_Exception) {
            return $this->checked_for_singleton_or_scoped_attributes[$class_name] = null;
        }
        $type = null;
        if (!empty($reflection->get_attributes(Singleton::class))) {
            $type = 'singleton';
        } elseif (!empty($reflection->get_attributes(Scoped::class))) {
            $type = 'scoped';
        }
        return $this->checked_for_singleton_or_scoped_attributes[$class_name] = $type;
    }
    /**
     * Determine if a given string is an alias.
     *
     * @param  string  $name
     */
    public function is_alias($name): bool
    {
        return isset($this->aliases[$name]);
    }
    /**
     * Register a binding with the container.
     *
     * @param  \Closure|string  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     * @return void
     *
     * @throws \TypeError
     * @throws ReflectionException
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if ($abstract instanceof Closure) {
            return $this->bind_based_on_closure_return_types($abstract, $concrete, $shared);
        }
        $this->drop_stale_instances($abstract);
        // If no concrete type was given, we will simply set the concrete type to the
        // abstract type. After that, the concrete type to be registered as shared
        // without being forced to state their classes in both of the parameters.
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        // If the factory is not a Closure, it means it is just a class name which is
        // bound into this container to the abstract type and we will just wrap it
        // up inside its own Closure to give us more convenience when extending.
        if (!$concrete instanceof Closure) {
            if (!is_string($concrete)) {
                throw new TypeError(self::class . '::bind(): Argument #2 ($concrete) must be of type Closure|string|null');
            }
            $concrete = $this->get_closure($abstract, $concrete);
        }
        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => $shared];
        // If the abstract type was already resolved in this container we'll fire the
        // rebound listener so that any objects which have already gotten resolved
        // can have their copy of the object updated via the listener callbacks.
        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }
    /**
     * Get the Closure to be used when building a type.
     *
     * @param  string  $abstract
     * @param  string  $concrete
     * @return \Closure
     */
    protected function get_closure($abstract, $concrete)
    {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete) {
                return $container->build($concrete);
            }
            return $container->resolve($concrete, $parameters, raiseEvents: false);
        };
    }
    /**
     * Determine if the container has a method binding.
     *
     * @param  string  $method
     */
    public function has_method_binding($method): bool
    {
        return isset($this->method_bindings[$method]);
    }
    /**
     * Bind a callback to resolve with Container::call.
     *
     * @param  array|string  $method
     * @param  \Closure  $callback
     */
    public function bind_method($method, $callback): void
    {
        $this->method_bindings[$this->parse_bind_method($method)] = $callback;
    }
    /**
     * Get the method to be bound in class@method format.
     *
     * @param  array|string  $method
     * @return string
     */
    protected function parse_bind_method($method)
    {
        if (is_array($method)) {
            return $method[0] . '@' . $method[1];
        }
        return $method;
    }
    /**
     * Get the method binding for the given method.
     *
     * @param  string  $method
     * @param  mixed  $instance
     */
    public function call_method_binding($method, $instance): mixed
    {
        return call_user_func($this->method_bindings[$method], $instance, $this);
    }
    /**
     * Add a contextual binding to the container.
     *
     * @param  string  $concrete
     * @param  \Closure|string  $abstract
     * @param  \Closure|string  $implementation
     */
    public function add_contextual_binding($concrete, $abstract, $implementation): void
    {
        $this->contextual[$concrete][$this->get_alias($abstract)] = $implementation;
    }
    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param  \Closure|string  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     */
    public function bind_if($abstract, $concrete = null, $shared = false): void
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }
    /**
     * Register a shared binding in the container.
     *
     * @param  \Closure|string  $abstract
     * @param  \Closure|string|null  $concrete
     */
    public function singleton($abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }
    /**
     * Register a shared binding if it hasn't already been registered.
     *
     * @param  \Closure|string  $abstract
     * @param  \Closure|string|null  $concrete
     */
    public function singleton_if($abstract, $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }
    /**
     * Register a scoped binding in the container.
     *
     * @param  \Closure|string  $abstract
     * @param  \Closure|string|null  $concrete
     */
    public function scoped($abstract, $concrete = null): void
    {
        $this->scoped_instances[] = $abstract;
        $this->singleton($abstract, $concrete);
    }
    /**
     * Register a scoped binding if it hasn't already been registered.
     *
     * @param  \Closure|string  $abstract
     * @param  \Closure|string|null  $concrete
     */
    public function scoped_if($abstract, $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->scoped($abstract, $concrete);
        }
    }
    /**
     * Register a binding with the container based on the given Closure's return types.
     *
     * @param  \Closure|string  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     * @return void
     */
    protected function bind_based_on_closure_return_types($abstract, $concrete = null, $shared = false)
    {
        $abstracts = $this->closure_return_types($abstract);
        $concrete = $abstract;
        foreach ($abstracts as $abstract) {
            $this->bind($abstract, $concrete, $shared);
        }
    }
    /**
     * "Extend" an abstract type in the container.
     *
     * @param  string  $abstract
     *
     * @throws \InvalidArgumentException
     */
    public function extend($abstract, Closure $closure): void
    {
        $abstract = $this->get_alias($abstract);
        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);
            $this->rebound($abstract);
        } else {
            $this->extenders[$abstract][] = $closure;
            if ($this->resolved($abstract)) {
                $this->rebound($abstract);
            }
        }
    }
    /**
     * Register an existing instance as shared in the container.
     *
     * @template TInstance of mixed
     *
     * @param  string  $abstract
     * @param  TInstance  $instance
     * @return TInstance
     */
    public function instance($abstract, $instance)
    {
        $this->remove_abstract_alias($abstract);
        $is_bound = $this->bound($abstract);
        unset($this->aliases[$abstract]);
        // We'll check to determine if this type has been bound before, and if it has
        // we will fire the rebound callbacks registered with the container and it
        // can be updated with consuming classes that have gotten resolved here.
        $this->instances[$abstract] = $instance;
        if ($is_bound) {
            $this->rebound($abstract);
        }
        return $instance;
    }
    /**
     * Remove an alias from the contextual binding alias cache.
     *
     * @param  string  $searched
     * @return void
     */
    protected function remove_abstract_alias($searched)
    {
        if (!isset($this->aliases[$searched])) {
            return;
        }
        foreach ($this->abstract_aliases as $abstract => $aliases) {
            foreach ($aliases as $index => $alias) {
                if ($alias == $searched) {
                    unset($this->abstract_aliases[$abstract][$index]);
                }
            }
        }
    }
    /**
     * Assign a set of tags to a given binding.
     *
     * @param  array|string  $abstracts
     * @param  mixed  ...$tags
     */
    public function tag($abstracts, $tags): void
    {
        $tags = is_array($tags) ? $tags : array_slice(func_get_args(), 1);
        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }
    /**
     * Resolve all of the bindings for a given tag.
     *
     * @param  string  $tag
     * @return iterable
     */
    public function tagged($tag): array|\Illuminate\Container\Rewindable_Generator
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }
        return new Rewindable_Generator(function () use ($tag) {
            foreach ($this->tags[$tag] as $abstract) {
                yield $this->make($abstract);
            }
        }, count($this->tags[$tag]));
    }
    /**
     * Alias a type to a different name.
     *
     * @param  string  $abstract
     * @param  string  $alias
     *
     * @throws \LogicException
     */
    public function alias($abstract, $alias): void
    {
        if ($alias === $abstract) {
            throw new LogicException("[{$abstract}] is aliased to itself.");
        }
        $this->remove_abstract_alias($alias);
        $this->aliases[$alias] = $abstract;
        $this->abstract_aliases[$abstract][] = $alias;
    }
    /**
     * Bind a new callback to an abstract's rebind event.
     *
     * @param  string  $abstract
     * @return mixed
     */
    public function rebinding($abstract, Closure $callback)
    {
        $this->rebound_callbacks[$abstract = $this->get_alias($abstract)][] = $callback;
        if ($this->bound($abstract)) {
            return $this->make($abstract);
        }
    }
    /**
     * Refresh an instance on the given target and method.
     *
     * @param  string  $abstract
     * @param  mixed  $target
     * @param  string  $method
     * @return mixed
     */
    public function refresh($abstract, $target, $method)
    {
        return $this->rebinding($abstract, function ($app, $instance) use ($target, $method): void {
            $target->{$method}($instance);
        });
    }
    /**
     * Fire the "rebound" callbacks for the given abstract type.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function rebound($abstract)
    {
        if (!$callbacks = $this->get_rebound_callbacks($abstract)) {
            return;
        }
        $instance = $this->make($abstract);
        foreach ($callbacks as $callback) {
            $callback($this, $instance);
        }
    }
    /**
     * Get the rebound callbacks for a given type.
     *
     * @param  string  $abstract
     * @return array
     */
    protected function get_rebound_callbacks($abstract)
    {
        return $this->rebound_callbacks[$abstract] ?? [];
    }
    /**
     * Wrap the given closure such that its dependencies will be injected when executed.
     *
     * @return \Closure
     */
    public function wrap(Closure $callback, array $parameters = [])
    {
        return fn(): mixed => $this->call($callback, $parameters);
    }
    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  callable|string  $callback
     * @param  array<string, mixed>  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function call($callback, array $parameters = [], $default_method = null)
    {
        $pushed_to_build_stack = false;
        if (($class_name = $this->get_class_for_callable($callback)) && !in_array($class_name, $this->build_stack, true)) {
            $this->build_stack[] = $class_name;
            $pushed_to_build_stack = true;
        }
        $result = Bound_Method::call($this, $callback, $parameters, $default_method);
        if ($pushed_to_build_stack) {
            array_pop($this->build_stack);
        }
        return $result;
    }
    /**
     * Get the class name for the given callback, if one can be determined.
     *
     * @param  callable|string  $callback
     * @return string|false
     */
    protected function get_class_for_callable($callback)
    {
        if (is_callable($callback) && !($reflector = new ReflectionFunction($callback(...)))->is_anonymous()) {
            return $reflector->get_closure_scope_class()->name ?? false;
        }
        return false;
    }
    /**
     * Get a closure to resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>  $abstract
     * @return ($abstract is class-string<TClass> ? \Closure(): TClass : \Closure(): mixed)
     */
    public function factory($abstract)
    {
        return fn() => $this->make($abstract);
    }
    /**
     * An alias function name for make().
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>|callable  $abstract
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function make_with($abstract, array $parameters = [])
    {
        return $this->make($abstract, $parameters);
    }
    /**
     * Resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>  $abstract
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function make($abstract, array $parameters = [])
    {
        return $this->resolve($abstract, $parameters);
    }
    /**
     * {@inheritdoc}
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>  $id
     * @return ($id is class-string<TClass> ? TClass : mixed)
     */
    public function get(string $id)
    {
        try {
            return $this->resolve($id);
        } catch (Exception $e) {
            if ($this->has($id) || $e instanceof Circular_Dependency_Exception) {
                throw $e;
            }
            throw new Entry_Not_Found_Exception($id, is_int($e->get_code()) ? $e->get_code() : 0, $e);
        }
    }
    /**
     * Resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>|callable  $abstract
     * @param  array  $parameters
     * @param  bool  $raiseEvents
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Illuminate\Contracts\Container\CircularDependencyException
     */
    protected function resolve($abstract, $parameters = [], $raise_events = true)
    {
        $abstract = $this->get_alias($abstract);
        // First we'll fire any event handlers which handle the "before" resolving of
        // specific types. This gives some hooks the chance to add various extends
        // calls to change the resolution of objects that they're interested in.
        if ($raise_events) {
            $this->fire_before_resolving_callbacks($abstract, $parameters);
        }
        $concrete = $this->get_contextual_concrete($abstract);
        $needs_contextual_build = !empty($parameters) || !is_null($concrete);
        // If an instance of the type is currently being managed as a singleton we'll
        // just return an existing instance instead of instantiating new instances
        // so the developer can keep using the same objects instance every time.
        if (isset($this->instances[$abstract]) && !$needs_contextual_build) {
            return $this->instances[$abstract];
        }
        $this->with[] = $parameters;
        if (is_null($concrete)) {
            $concrete = $this->get_concrete($abstract);
        }
        // We're ready to instantiate an instance of the concrete type registered for
        // the binding. This will instantiate the types, as well as resolve any of
        // its "nested" dependencies recursively until all have gotten resolved.
        $object = $this->is_buildable($concrete, $abstract) ? $this->build($concrete) : $this->make($concrete);
        // If we defined any extenders for this type, we'll need to spin through them
        // and apply them to the object being built. This allows for the extension
        // of services, such as changing configuration or decorating the object.
        foreach ($this->get_extenders($abstract) as $extender) {
            $object = $extender($object, $this);
        }
        // If the requested type is registered as a singleton we'll want to cache off
        // the instances in "memory" so we can return it later without creating an
        // entirely new instance of an object on each subsequent request for it.
        if ($this->is_shared($abstract) && !$needs_contextual_build) {
            $this->instances[$abstract] = $object;
        }
        if ($raise_events) {
            $this->fire_resolving_callbacks($abstract, $object);
        }
        // Before returning, we will also set the resolved flag to "true" and pop off
        // the parameter overrides for this build. After those two things are done
        // we will be ready to return back the fully constructed class instance.
        if (!$needs_contextual_build) {
            $this->resolved[$abstract] = true;
        }
        array_pop($this->with);
        return $object;
    }
    /**
     * Get the concrete type for a given abstract.
     *
     * @param  string|callable  $abstract
     * @return mixed
     */
    protected function get_concrete($abstract)
    {
        // If we don't have a registered resolver or concrete for the type, we'll just
        // assume each type is a concrete name and will attempt to resolve it as is
        // since the container should be able to resolve concretes automatically.
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }
        if ($this->environment_resolver === null || ($this->checked_for_attribute_bindings[$abstract] ?? false) || !is_string($abstract)) {
            return $abstract;
        }
        return $this->get_concrete_binding_from_attributes($abstract);
    }
    /**
     * Get the concrete binding for an abstract from the Bind attribute.
     *
     * @param  string  $abstract
     * @return mixed
     */
    protected function get_concrete_binding_from_attributes($abstract)
    {
        $this->checked_for_attribute_bindings[$abstract] = true;
        try {
            $reflected = new ReflectionClass($abstract);
        } catch (Reflection_Exception) {
            return $abstract;
        }
        $bind_attributes = $reflected->get_attributes(Bind::class);
        if ($bind_attributes === []) {
            return $abstract;
        }
        $concrete = $maybe_concrete = null;
        foreach ($bind_attributes as $reflected_attribute) {
            $instance = $reflected_attribute->new_instance();
            if ($instance->environments === ['*']) {
                $maybe_concrete = $instance->concrete;
                continue;
            }
            if ($this->current_environment_is($instance->environments)) {
                $concrete = $instance->concrete;
                break;
            }
        }
        if ($maybe_concrete !== null && $concrete === null) {
            $concrete = $maybe_concrete;
        }
        if ($concrete === null) {
            return $abstract;
        }
        match ($this->get_scoped_typed($reflected)) {
            'scoped' => $this->scoped($abstract, $concrete),
            'singleton' => $this->singleton($abstract, $concrete),
            null => $this->bind($abstract, $concrete),
        };
        return $this->bindings[$abstract]['concrete'];
    }
    /**
     * Get the contextual concrete binding for the given abstract.
     *
     * @param  string|callable  $abstract
     * @return \Closure|string|array|null
     */
    protected function get_contextual_concrete($abstract)
    {
        if (!is_null($binding = $this->find_in_contextual_bindings($abstract))) {
            return $binding;
        }
        // Next we need to see if a contextual binding might be bound under an alias of the
        // given abstract type. So, we will need to check if any aliases exist with this
        // type and then spin through them and check for contextual bindings on these.
        if (empty($this->abstract_aliases[$abstract])) {
            return;
        }
        foreach ($this->abstract_aliases[$abstract] as $alias) {
            if (!is_null($binding = $this->find_in_contextual_bindings($alias))) {
                return $binding;
            }
        }
    }
    /**
     * Find the concrete binding for the given abstract in the contextual binding array.
     *
     * @param  string|callable  $abstract
     * @return \Closure|string|null
     */
    protected function find_in_contextual_bindings($abstract)
    {
        return $this->contextual[end($this->build_stack)][$abstract] ?? null;
    }
    /**
     * Determine if the given concrete is buildable.
     *
     * @param  mixed  $concrete
     * @param  string  $abstract
     */
    protected function is_buildable($concrete, $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }
    /**
     * Instantiate a concrete instance of the given type.
     *
     * @template TClass of object
     *
     * @param  \Closure(static, array): TClass|class-string<TClass>  $concrete
     * @return TClass
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Illuminate\Contracts\Container\CircularDependencyException
     */
    public function build($concrete)
    {
        // If the concrete type is actually a Closure, we will just execute it and
        // hand back the results of the functions, which allows functions to be
        // used as resolvers for more fine-tuned resolution of these objects.
        if ($concrete instanceof Closure) {
            $this->build_stack[] = spl_object_hash($concrete);
            try {
                return $concrete($this, $this->get_last_parameter_override());
            } finally {
                array_pop($this->build_stack);
            }
        }
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (Reflection_Exception $e) {
            throw new Binding_Resolution_Exception("Target class [{$concrete}] does not exist.", 0, $e);
        }
        // If the type is not instantiable, the developer is attempting to resolve
        // an abstract type such as an Interface or Abstract Class and there is
        // no binding registered for the abstractions so we need to bail out.
        if (!$reflector->is_instantiable()) {
            return $this->not_instantiable($concrete);
        }
        if (is_a($concrete, Self_Building::class, true) && !in_array($concrete, $this->build_stack, true)) {
            return $this->build_self_building_instance($concrete, $reflector);
        }
        $this->build_stack[] = $concrete;
        $constructor = $reflector->get_constructor();
        // If there are no constructors, that means there are no dependencies then
        // we can just resolve the instances of the objects right away, without
        // resolving any other types or dependencies out of these containers.
        if (is_null($constructor)) {
            array_pop($this->build_stack);
            $this->fire_after_resolving_attribute_callbacks($reflector->get_attributes(), $instance = new $concrete());
            return $instance;
        }
        $dependencies = $constructor->get_parameters();
        // Once we have all the constructor's parameters we can create each of the
        // dependency instances and then use the reflection instances to make a
        // new instance of this class, injecting the created dependencies in.
        try {
            $instances = $this->resolve_dependencies($dependencies);
        } finally {
            array_pop($this->build_stack);
        }
        $this->fire_after_resolving_attribute_callbacks($reflector->get_attributes(), $instance = new $concrete(...$instances));
        return $instance;
    }
    /**
     * Instantiate a concrete instance of the given self building type.
     *
     * @template TClass of object
     *
     * @param  object{'newInstance': \Closure(static, array): TClass|class-string<TClass>}  $concrete
     * @param  \ReflectionClass  $reflector
     * @return TClass
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function build_self_building_instance($concrete, $reflector)
    {
        if (!method_exists($concrete, 'newInstance')) {
            throw new Binding_Resolution_Exception("No newInstance method exists for [{$concrete}].");
        }
        $this->build_stack[] = $concrete;
        $instance = $this->call([$concrete, 'newInstance']);
        array_pop($this->build_stack);
        $this->fire_after_resolving_attribute_callbacks($reflector->get_attributes(), $instance);
        return $instance;
    }
    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param  \ReflectionParameter[]  $dependencies
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolve_dependencies(array $dependencies): array
    {
        $results = [];
        foreach ($dependencies as $dependency) {
            // If the dependency has an override for this particular build we will use
            // that instead as the value. Otherwise, we will continue with this run
            // of resolutions and let reflection attempt to determine the result.
            if ($this->has_parameter_override($dependency)) {
                $results[] = $this->get_parameter_override($dependency);
                continue;
            }
            $result = null;
            if (!is_null($attribute = Util::get_contextual_attribute_from_dependency($dependency))) {
                $result = $this->resolve_from_attribute($attribute);
            }
            // If the class is null, it means the dependency is a string or some other
            // primitive type which we can not resolve since it is not a class and
            // we will just bomb out with an error since we have no-where to go.
            $result ??= is_null(Util::get_parameter_class_name($dependency)) ? $this->resolve_primitive($dependency) : $this->resolve_class($dependency);
            $this->fire_after_resolving_attribute_callbacks($dependency->get_attributes(), $result);
            if ($dependency->is_variadic()) {
                $results = array_merge($results, $result);
            } else {
                $results[] = $result;
            }
        }
        return $results;
    }
    /**
     * Determine if the given dependency has a parameter override.
     *
     * @param  \ReflectionParameter  $dependency
     */
    protected function has_parameter_override($dependency): bool
    {
        return array_key_exists($dependency->name, $this->get_last_parameter_override());
    }
    /**
     * Get a parameter override for a dependency.
     *
     * @param  \ReflectionParameter  $dependency
     * @return mixed
     */
    protected function get_parameter_override($dependency)
    {
        return $this->get_last_parameter_override()[$dependency->name];
    }
    /**
     * Get the last parameter override.
     *
     * @return array
     */
    protected function get_last_parameter_override()
    {
        return count($this->with) ? array_last($this->with) : [];
    }
    /**
     * Resolve a non-class hinted primitive dependency.
     *
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolve_primitive(ReflectionParameter $parameter)
    {
        if (!is_null($concrete = $this->get_contextual_concrete('$' . $parameter->get_name()))) {
            return Util::unwrap_if_closure($concrete, $this);
        }
        if ($parameter->is_default_value_available()) {
            return $parameter->get_default_value();
        }
        if ($parameter->is_variadic()) {
            return [];
        }
        if ($parameter->has_type() && $parameter->allows_null()) {
            return null;
        }
        $this->unresolvable_primitive($parameter);
    }
    /**
     * Resolve a class based dependency from the container.
     *
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolve_class(ReflectionParameter $parameter)
    {
        $class_name = Util::get_parameter_class_name($parameter);
        // First we will check if a default value has been defined for the parameter.
        // If it has, and no explicit binding exists, we should return it to avoid
        // overriding any of the developer specified defaults for the parameters.
        if ($parameter->is_default_value_available() && !$this->bound($class_name) && $this->find_in_contextual_bindings($class_name) === null) {
            return $parameter->get_default_value();
        }
        try {
            return $parameter->is_variadic() ? $this->resolve_variadic_class($parameter) : $this->make($class_name);
        } catch (Binding_Resolution_Exception $e) {
            if ($parameter->is_variadic()) {
                array_pop($this->with);
                return [];
            }
            throw $e;
        }
    }
    /**
     * Resolve a class based variadic dependency from the container.
     *
     * @return mixed
     */
    protected function resolve_variadic_class(ReflectionParameter $parameter)
    {
        $class_name = Util::get_parameter_class_name($parameter);
        $abstract = $this->get_alias($class_name);
        if (!is_array($concrete = $this->get_contextual_concrete($abstract))) {
            return $this->make($class_name);
        }
        return array_map(fn($abstract) => $this->resolve($abstract), $concrete);
    }
    /**
     * Resolve a dependency based on an attribute.
     *
     * @return mixed
     */
    public function resolve_from_attribute(Reflection_Attribute $attribute)
    {
        $handler = $this->contextual_attributes[$attribute->get_name()] ?? null;
        $instance = $attribute->new_instance();
        if (is_null($handler) && method_exists($instance, 'resolve')) {
            $handler = $instance->resolve(...);
        }
        if (is_null($handler)) {
            throw new Binding_Resolution_Exception("Contextual binding attribute [{$attribute->get_name()}] has no registered handler.");
        }
        return $handler($instance, $this);
    }
    /**
     * Throw an exception that the concrete is not instantiable.
     *
     * @param  string  $concrete
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function not_instantiable($concrete)
    {
        if (!empty($this->build_stack)) {
            $previous = implode(', ', $this->build_stack);
            $message = "Target [{$concrete}] is not instantiable while building [{$previous}].";
        } else {
            $message = "Target [{$concrete}] is not instantiable.";
        }
        throw new Binding_Resolution_Exception($message);
    }
    /**
     * Throw an exception for an unresolvable primitive.
     *
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function unresolvable_primitive(ReflectionParameter $parameter): never
    {
        $message = "Unresolvable dependency resolving [{$parameter}] in class {$parameter->get_declaring_class()->get_name()}";
        throw new Binding_Resolution_Exception($message);
    }
    /**
     * Register a new before resolving callback for all types.
     *
     * @param  \Closure|string  $abstract
     */
    public function before_resolving($abstract, ?Closure $callback = null): void
    {
        if (is_string($abstract)) {
            $abstract = $this->get_alias($abstract);
        }
        if ($abstract instanceof Closure && is_null($callback)) {
            $this->global_before_resolving_callbacks[] = $abstract;
        } else {
            $this->before_resolving_callbacks[$abstract][] = $callback;
        }
    }
    /**
     * Register a new resolving callback.
     *
     * @param  \Closure|string  $abstract
     */
    public function resolving($abstract, ?Closure $callback = null): void
    {
        if (is_string($abstract)) {
            $abstract = $this->get_alias($abstract);
        }
        if (is_null($callback) && $abstract instanceof Closure) {
            $this->global_resolving_callbacks[] = $abstract;
        } else {
            $this->resolving_callbacks[$abstract][] = $callback;
        }
    }
    /**
     * Register a new after resolving callback for all types.
     *
     * @param  \Closure|string  $abstract
     */
    public function after_resolving($abstract, ?Closure $callback = null): void
    {
        if (is_string($abstract)) {
            $abstract = $this->get_alias($abstract);
        }
        if ($abstract instanceof Closure && is_null($callback)) {
            $this->global_after_resolving_callbacks[] = $abstract;
        } else {
            $this->after_resolving_callbacks[$abstract][] = $callback;
        }
    }
    /**
     * Register a new after resolving attribute callback for all types.
     */
    public function after_resolving_attribute(string $attribute, \Closure $callback): void
    {
        $this->after_resolving_attribute_callbacks[$attribute][] = $callback;
    }
    /**
     * Fire all of the before resolving callbacks.
     *
     * @param  string  $abstract
     * @param  array  $parameters
     * @return void
     */
    protected function fire_before_resolving_callbacks($abstract, $parameters = [])
    {
        $this->fire_before_callback_array($abstract, $parameters, $this->global_before_resolving_callbacks);
        foreach ($this->before_resolving_callbacks as $type => $callbacks) {
            if ($type === $abstract || is_subclass_of($abstract, $type)) {
                $this->fire_before_callback_array($abstract, $parameters, $callbacks);
            }
        }
    }
    /**
     * Fire an array of callbacks with an object.
     *
     * @param  string  $abstract
     * @param  array  $parameters
     * @return void
     */
    protected function fire_before_callback_array($abstract, $parameters, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($abstract, $parameters, $this);
        }
    }
    /**
     * Fire all of the resolving callbacks.
     *
     * @param  string  $abstract
     * @param  mixed  $object
     * @return void
     */
    protected function fire_resolving_callbacks($abstract, $object)
    {
        $this->fire_callback_array($object, $this->global_resolving_callbacks);
        $this->fire_callback_array($object, $this->get_callbacks_for_type($abstract, $object, $this->resolving_callbacks));
        $this->fire_after_resolving_callbacks($abstract, $object);
    }
    /**
     * Fire all of the after resolving callbacks.
     *
     * @param  string  $abstract
     * @param  mixed  $object
     * @return void
     */
    protected function fire_after_resolving_callbacks($abstract, $object)
    {
        $this->fire_callback_array($object, $this->global_after_resolving_callbacks);
        $this->fire_callback_array($object, $this->get_callbacks_for_type($abstract, $object, $this->after_resolving_callbacks));
    }
    /**
     * Fire all of the after resolving attribute callbacks.
     *
     * @param  \ReflectionAttribute[]  $attributes
     * @param  mixed  $object
     */
    public function fire_after_resolving_attribute_callbacks(array $attributes, $object): void
    {
        foreach ($attributes as $attribute) {
            if (is_a($attribute->get_name(), Contextual_Attribute::class, true)) {
                $instance = $attribute->new_instance();
                if (method_exists($instance, 'after')) {
                    $instance->after($instance, $object, $this);
                }
            }
            $callbacks = $this->get_callbacks_for_type($attribute->get_name(), $object, $this->after_resolving_attribute_callbacks);
            foreach ($callbacks as $callback) {
                $callback($attribute->new_instance(), $object, $this);
            }
        }
    }
    /**
     * Get all callbacks for a given type.
     *
     * @param  string  $abstract
     * @param  object  $object
     */
    protected function get_callbacks_for_type($abstract, $object, array $callbacks_per_type): array
    {
        $results = [];
        foreach ($callbacks_per_type as $type => $callbacks) {
            if ($type === $abstract || $object instanceof $type) {
                $results = array_merge($results, $callbacks);
            }
        }
        return $results;
    }
    /**
     * Fire an array of callbacks with an object.
     *
     * @param  mixed  $object
     * @return void
     */
    protected function fire_callback_array($object, array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($object, $this);
        }
    }
    /**
     * Get the name of the binding the container is currently resolving.
     *
     * @return class-string|string|null
     */
    public function currently_resolving()
    {
        return array_last($this->build_stack) ?: null;
    }
    /**
     * Get the container's bindings.
     *
     * @return array
     */
    public function get_bindings()
    {
        return $this->bindings;
    }
    /**
     * Get the alias for an abstract if available.
     *
     * @param  string  $abstract
     * @return string
     */
    public function get_alias($abstract)
    {
        return isset($this->aliases[$abstract]) ? $this->get_alias($this->aliases[$abstract]) : $abstract;
    }
    /**
     * Get the extender callbacks for a given type.
     *
     * @param  string  $abstract
     * @return array
     */
    protected function get_extenders($abstract)
    {
        return $this->extenders[$this->get_alias($abstract)] ?? [];
    }
    /**
     * Remove all of the extender callbacks for a given type.
     *
     * @param  string  $abstract
     */
    public function forget_extenders($abstract): void
    {
        unset($this->extenders[$this->get_alias($abstract)]);
    }
    /**
     * Drop all of the stale instances and aliases.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function drop_stale_instances($abstract)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }
    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param  string  $abstract
     */
    public function forget_instance($abstract): void
    {
        unset($this->instances[$abstract]);
    }
    /**
     * Clear all of the instances from the container.
     */
    public function forget_instances(): void
    {
        $this->instances = [];
    }
    /**
     * Clear all of the scoped instances from the container.
     */
    public function forget_scoped_instances(): void
    {
        foreach ($this->scoped_instances as $scoped) {
            if ($scoped instanceof Closure) {
                foreach ($this->closure_return_types($scoped) as $type) {
                    unset($this->instances[$type]);
                }
            } else {
                unset($this->instances[$scoped]);
            }
        }
    }
    /**
     * Set the callback which determines the current container environment.
     *
     * @param  (callable(array<int, string>|string): bool|string)|null  $callback
     */
    public function resolve_environment_using(?callable $callback): void
    {
        $this->environment_resolver = $callback;
    }
    /**
     * Determine the environment for the container.
     *
     * @param  array<int, string>|string  $environments
     * @return bool
     */
    public function current_environment_is($environments)
    {
        return $this->environment_resolver === null ? false : call_user_func($this->environment_resolver, $environments);
    }
    /**
     * Flush the container of all bindings and resolved instances.
     */
    public function flush(): void
    {
        $this->aliases = [];
        $this->resolved = [];
        $this->bindings = [];
        $this->instances = [];
        $this->abstract_aliases = [];
        $this->scoped_instances = [];
        $this->checked_for_attribute_bindings = [];
        $this->checked_for_singleton_or_scoped_attributes = [];
    }
    /**
     * Get the globally available instance of the container.
     *
     * @return static
     */
    public static function get_instance()
    {
        return static::$instance ??= new static();
    }
    /**
     * Set the shared instance of the container.
     *
     * @return \Illuminate\Contracts\Container\Container|static
     */
    public static function set_instance(?Container_Contract $container = null): ?\Illuminate\Contracts\Container\Container
    {
        return static::$instance = $container;
    }
    /**
     * Determine if a given offset exists.
     *
     * @param  string  $key
     */
    public function offsetExists($key): bool
    {
        return $this->bound($key);
    }
    /**
     * Get the value at a given offset.
     *
     * @param  string  $key
     */
    public function offsetGet($key): mixed
    {
        return $this->make($key);
    }
    /**
     * Set the value at a given offset.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function offsetSet($key, $value): void
    {
        $this->bind($key, $value instanceof Closure ? $value : fn() => $value);
    }
    /**
     * Unset the value at a given offset.
     *
     * @param  string  $key
     */
    public function offsetUnset($key): void
    {
        unset($this->bindings[$key], $this->instances[$key], $this->resolved[$key]);
    }
    /**
     * Dynamically access container services.
     */
    public function __get(string $key): mixed
    {
        return $this[$key];
    }
    /**
     * Dynamically set container services.
     *
     * @return void
     */
    public function __set(string $key, mixed $value)
    {
        $this[$key] = $value;
    }
}