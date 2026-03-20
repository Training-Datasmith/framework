<?php

declare (strict_types=1);
namespace Illuminate\Auth\Access;

use Closure;
use Exception;
use Illuminate\Auth\Access\Events\Gate_Evaluated;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Attributes\Use_Policy;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;
class Gate implements Gate_Contract
{
    use Handles_Authorization;
    /**
     * The user resolver callable.
     *
     * @var callable
     */
    protected $user_resolver;
    /**
     * All of the defined abilities.
     *
     * @var array
     */
    protected $abilities = [];
    /**
     * All of the registered before callbacks.
     *
     * @var array
     */
    protected $before_callbacks = [];
    /**
     * All of the registered after callbacks.
     *
     * @var array
     */
    protected $after_callbacks = [];
    /**
     * All of the defined abilities using class@method notation.
     *
     * @var array
     */
    protected $string_callbacks = [];
    /**
     * The default denial response for gates and policies.
     *
     * @var \Illuminate\Auth\Access\Response|null
     */
    protected $default_denial_response;
    /**
     * The callback to be used to guess policy names.
     *
     * @var callable|null
     */
    protected $guess_policy_names_using_callback;
    /**
     * Create a new gate instance.
     */
    public function __construct(
        /**
         * The container instance.
         */
        protected \Illuminate\Contracts\Container\Container $container,
        callable $user_resolver,
        array $abilities = [],
        /**
         * All of the defined policies.
         */
        protected array $policies = [],
        array $before_callbacks = [],
        array $after_callbacks = [],
        ?callable $guess_policy_names_using_callback = null
    )
    {
        $this->abilities = $abilities;
        $this->user_resolver = $user_resolver;
        $this->after_callbacks = $after_callbacks;
        $this->before_callbacks = $before_callbacks;
        $this->guess_policy_names_using_callback = $guess_policy_names_using_callback;
    }
    /**
     * Determine if a given ability has been defined.
     *
     * @param  \UnitEnum|array|string  $ability
     */
    public function has($ability): bool
    {
        $abilities = is_array($ability) ? $ability : func_get_args();
        foreach ($abilities as $ability) {
            if (!isset($this->abilities[enum_value($ability)])) {
                return false;
            }
        }
        return true;
    }
    /**
     * Perform an on-demand authorization check. Throw an authorization exception if the condition or callback is false.
     *
     * @param  \Illuminate\Auth\Access\Response|\Closure|bool  $condition
     * @param  string|null  $message
     * @param  string|null  $code
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function allow_if($condition, $message = null, $code = null)
    {
        return $this->authorize_on_demand($condition, $message, $code, true);
    }
    /**
     * Perform an on-demand authorization check. Throw an authorization exception if the condition or callback is true.
     *
     * @param  \Illuminate\Auth\Access\Response|\Closure|bool  $condition
     * @param  string|null  $message
     * @param  string|null  $code
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function deny_if($condition, $message = null, $code = null)
    {
        return $this->authorize_on_demand($condition, $message, $code, false);
    }
    /**
     * Authorize a given condition or callback.
     *
     * @param  \Illuminate\Auth\Access\Response|\Closure|bool  $condition
     * @param  string|null  $message
     * @param  string|null  $code
     * @param  bool  $allowWhenResponseIs
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function authorize_on_demand($condition, $message, $code, $allow_when_response_is): \Illuminate\Auth\Access\Response
    {
        $user = $this->resolve_user();
        if ($condition instanceof Closure) {
            $response = $this->can_be_called_with_user($user, $condition) ? $condition($user) : new Response(false, $message, $code);
        } else {
            $response = $condition;
        }
        return ($response instanceof Response ? $response : new Response((bool) $response === $allow_when_response_is, $message, $code))->authorize();
    }
    /**
     * Define a new ability.
     *
     * @param  \UnitEnum|string  $ability
     * @param  callable|array|string  $callback
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function define($ability, $callback): static
    {
        $ability = enum_value($ability);
        if (is_array($callback) && isset($callback[0]) && is_string($callback[0])) {
            $callback = $callback[0] . '@' . $callback[1];
        }
        if (is_callable($callback)) {
            $this->abilities[$ability] = $callback;
        } elseif (is_string($callback)) {
            $this->string_callbacks[$ability] = $callback;
            $this->abilities[$ability] = $this->build_ability_callback($ability, $callback);
        } else {
            throw new InvalidArgumentException("Callback must be a callable, callback array, or a 'Class@method' string.");
        }
        return $this;
    }
    /**
     * Define abilities for a resource.
     *
     * @param  string  $name
     * @param  string  $class
     * @return $this
     */
    public function resource($name, $class, ?array $abilities = null): static
    {
        $abilities = $abilities ?: ['viewAny' => 'viewAny', 'view' => 'view', 'create' => 'create', 'update' => 'update', 'delete' => 'delete'];
        foreach ($abilities as $ability => $method) {
            $this->define($name . '.' . $ability, $class . '@' . $method);
        }
        return $this;
    }
    /**
     * Create the ability callback for a callback string.
     *
     * @param  string  $ability
     * @param  string  $callback
     * @return \Closure
     */
    protected function build_ability_callback($ability, $callback)
    {
        return function () use ($ability, $callback) {
            if (str_contains($callback, '@')) {
                [$class, $method] = Str::parse_callback($callback);
            } else {
                $class = $callback;
            }
            $policy = $this->resolve_policy($class);
            $arguments = func_get_args();
            $user = array_shift($arguments);
            $result = $this->call_policy_before($policy, $user, $ability, $arguments);
            if (!is_null($result)) {
                return $result;
            }
            return isset($method) ? $policy->{$method}(...func_get_args()) : $policy(...func_get_args());
        };
    }
    /**
     * Define a policy class for a given class type.
     *
     * @param  string  $class
     * @param  string  $policy
     * @return $this
     */
    public function policy($class, $policy): static
    {
        $this->policies[$class] = $policy;
        return $this;
    }
    /**
     * Register a callback to run before all Gate checks.
     *
     * @return $this
     */
    public function before(callable $callback): static
    {
        $this->before_callbacks[] = $callback;
        return $this;
    }
    /**
     * Register a callback to run after all Gate checks.
     *
     * @return $this
     */
    public function after(callable $callback): static
    {
        $this->after_callbacks[] = $callback;
        return $this;
    }
    /**
     * Determine if all of the given abilities should be granted for the current user.
     *
     * @param  iterable|\UnitEnum|string  $ability
     * @param  mixed  $arguments
     * @return bool
     */
    public function allows($ability, $arguments = [])
    {
        return $this->check($ability, $arguments);
    }
    /**
     * Determine if any of the given abilities should be denied for the current user.
     *
     * @param  iterable|\UnitEnum|string  $ability
     * @param  mixed  $arguments
     */
    public function denies($ability, $arguments = []): bool
    {
        return !$this->allows($ability, $arguments);
    }
    /**
     * Determine if all of the given abilities should be granted for the current user.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     * @return bool
     */
    public function check($abilities, $arguments = [])
    {
        return (new Collection($abilities))->every(fn($ability) => $this->inspect($ability, $arguments)->allowed());
    }
    /**
     * Determine if any one of the given abilities should be granted for the current user.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     * @return bool
     */
    public function any($abilities, $arguments = [])
    {
        return (new Collection($abilities))->contains(fn($ability) => $this->check($ability, $arguments));
    }
    /**
     * Determine if all of the given abilities should be denied for the current user.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     */
    public function none($abilities, $arguments = []): bool
    {
        return !$this->any($abilities, $arguments);
    }
    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param  \UnitEnum|string  $ability
     * @param  mixed  $arguments
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = []): \Illuminate\Auth\Access\Response
    {
        return $this->inspect($ability, $arguments)->authorize();
    }
    /**
     * Inspect the user for the given ability.
     *
     * @param  \UnitEnum|string  $ability
     * @param  mixed  $arguments
     * @return \Illuminate\Auth\Access\Response
     */
    public function inspect($ability, $arguments = [])
    {
        try {
            $result = $this->raw(enum_value($ability), $arguments);
            if ($result instanceof Response) {
                return $result;
            }
            return $result ? Response::allow() : $this->default_denial_response ?? Response::deny();
        } catch (Authorization_Exception $e) {
            return $e->to_response();
        }
    }
    /**
     * Get the raw result from the authorization callback.
     *
     * @param  string  $ability
     * @param  mixed  $arguments
     * @return mixed
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function raw($ability, $arguments = [])
    {
        $arguments = Arr::wrap($arguments);
        $user = $this->resolve_user();
        // First we will call the "before" callbacks for the Gate. If any of these give
        // back a non-null response, we will immediately return that result in order
        // to let the developers override all checks for some authorization cases.
        $result = $this->call_before_callbacks($user, $ability, $arguments);
        if (is_null($result)) {
            $result = $this->call_auth_callback($user, $ability, $arguments);
        }
        // After calling the authorization callback, we will call the "after" callbacks
        // that are registered with the Gate, which allows a developer to do logging
        // if that is required for this application. Then we'll return the result.
        return tap($this->call_after_callbacks($user, $ability, $arguments, $result), function ($result) use ($user, $ability, $arguments): void {
            $this->dispatch_gate_evaluated_event($user, $ability, $arguments, $result);
        });
    }
    /**
     * Determine whether the callback/method can be called with the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  \Closure|string|array  $class
     * @param  string|null  $method
     * @return bool
     */
    protected function can_be_called_with_user($user, $class, $method = null)
    {
        if (!is_null($user)) {
            return true;
        }
        if (!is_null($method)) {
            return $this->method_allows_guests($class, $method);
        }
        if (is_array($class)) {
            $class_name = is_string($class[0]) ? $class[0] : $class[0]::class;
            return $this->method_allows_guests($class_name, $class[1]);
        }
        return $this->callback_allows_guests($class);
    }
    /**
     * Determine if the given class method allows guests.
     *
     * @param  string  $class
     * @param  string  $method
     * @return bool
     */
    protected function method_allows_guests($class, $method)
    {
        try {
            $reflection = new ReflectionClass($class);
            $method = $reflection->get_method($method);
        } catch (Exception) {
            return false;
        }
        if ($method) {
            $parameters = $method->get_parameters();
            return isset($parameters[0]) && $this->parameter_allows_guests($parameters[0]);
        }
        return false;
    }
    /**
     * Determine if the callback allows guests.
     *
     * @param  callable  $callback
     *
     * @throws \ReflectionException
     */
    protected function callback_allows_guests($callback): bool
    {
        $parameters = (new ReflectionFunction($callback))->get_parameters();
        return isset($parameters[0]) && $this->parameter_allows_guests($parameters[0]);
    }
    /**
     * Determine if the given parameter allows guests.
     *
     * @param  \ReflectionParameter  $parameter
     */
    protected function parameter_allows_guests($parameter): bool
    {
        if ($parameter->has_type() && $parameter->allows_null()) {
            return true;
        }
        return $parameter->is_default_value_available() && is_null($parameter->get_default_value());
    }
    /**
     * Resolve and call the appropriate authorization callback.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  string  $ability
     * @return bool
     */
    protected function call_auth_callback($user, $ability, array $arguments)
    {
        $callback = $this->resolve_auth_callback($user, $ability, $arguments);
        return $callback($user, ...$arguments);
    }
    /**
     * Call all of the before callbacks and return if a result is given.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  string  $ability
     * @return bool|null
     */
    protected function call_before_callbacks($user, $ability, array $arguments)
    {
        foreach ($this->before_callbacks as $before) {
            if (!$this->can_be_called_with_user($user, $before)) {
                continue;
            }
            if (!is_null($result = $before($user, $ability, $arguments))) {
                return $result;
            }
        }
    }
    /**
     * Call all of the after callbacks with check result.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $ability
     * @param  bool|null  $result
     * @return bool|null
     */
    protected function call_after_callbacks($user, $ability, array $arguments, $result)
    {
        foreach ($this->after_callbacks as $after) {
            if (!$this->can_be_called_with_user($user, $after)) {
                continue;
            }
            $after_result = $after($user, $ability, $result, $arguments);
            $result ??= $after_result;
        }
        return $result;
    }
    /**
     * Dispatch a gate evaluation event.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  string  $ability
     * @param  bool|null  $result
     * @return void
     */
    protected function dispatch_gate_evaluated_event($user, $ability, array $arguments, $result)
    {
        if ($this->container->bound(Dispatcher::class)) {
            $this->container->make(Dispatcher::class)->dispatch(new Gate_Evaluated($user, $ability, $result, $arguments));
        }
    }
    /**
     * Resolve the callable for the given ability and arguments.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  string  $ability
     * @return callable
     */
    protected function resolve_auth_callback($user, $ability, array $arguments)
    {
        if (isset($arguments[0]) && !is_null($policy = $this->get_policy_for($arguments[0])) && $callback = $this->resolve_policy_callback($user, $ability, $arguments, $policy)) {
            return $callback;
        }
        if (isset($this->string_callbacks[$ability])) {
            [$class, $method] = Str::parse_callback($this->string_callbacks[$ability]);
            if ($this->can_be_called_with_user($user, $class, $method ?: '__invoke')) {
                return $this->abilities[$ability];
            }
        }
        if (isset($this->abilities[$ability]) && $this->can_be_called_with_user($user, $this->abilities[$ability])) {
            return $this->abilities[$ability];
        }
        return function (): void {
        };
    }
    /**
     * Get a policy instance for a given class.
     *
     * @param  object|string  $class
     * @return mixed
     */
    public function get_policy_for($class)
    {
        if (is_object($class)) {
            $class = $class::class;
        }
        if (!is_string($class)) {
            return;
        }
        if (isset($this->policies[$class])) {
            return $this->resolve_policy($this->policies[$class]);
        }
        $policy = $this->get_policy_from_attribute($class);
        if (!is_null($policy)) {
            return $this->resolve_policy($policy);
        }
        foreach ($this->guess_policy_name($class) as $guessed_policy) {
            if (class_exists($guessed_policy)) {
                return $this->resolve_policy($guessed_policy);
            }
        }
        foreach ($this->policies as $expected => $policy) {
            if (is_subclass_of($class, $expected)) {
                return $this->resolve_policy($policy);
            }
        }
    }
    /**
     * Get the policy class from the class attribute.
     *
     * @param  class-string<*>  $class
     * @return class-string<*>|null
     */
    protected function get_policy_from_attribute(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }
        $attributes = (new ReflectionClass($class))->get_attributes(Use_Policy::class);
        return $attributes !== [] ? $attributes[0]->new_instance()->class : null;
    }
    /**
     * Guess the policy name for the given class.
     *
     * @param  string  $class
     */
    protected function guess_policy_name($class): array
    {
        if ($this->guess_policy_names_using_callback) {
            return Arr::wrap(call_user_func($this->guess_policy_names_using_callback, $class));
        }
        $class_dirname = str_replace('/', '\\', dirname(str_replace('\\', '/', $class)));
        $class_dirname_segments = explode('\\', $class_dirname);
        return Arr::wrap(Collection::times(count($class_dirname_segments), function ($index) use ($class, $class_dirname_segments): string {
            $class_dirname = implode('\\', array_slice($class_dirname_segments, 0, $index));
            return $class_dirname . '\Policies\\' . class_basename($class) . 'Policy';
        })->when(str_contains($class_dirname, '\Models\\'), fn($collection): \Illuminate\Support\Collection => $collection->concat([str_replace('\Models\\', '\Policies\\', $class_dirname) . '\\' . class_basename($class) . 'Policy'])->concat([str_replace('\Models\\', '\Models\Policies\\', $class_dirname) . '\\' . class_basename($class) . 'Policy']))->reverse()->values()->first(fn($class): bool => class_exists($class)) ?: [$class_dirname . '\Policies\\' . class_basename($class) . 'Policy']);
    }
    /**
     * Specify a callback to be used to guess policy names.
     *
     * @return $this
     */
    public function guess_policy_names_using(callable $callback): static
    {
        $this->guess_policy_names_using_callback = $callback;
        return $this;
    }
    /**
     * Build a policy class instance of the given type.
     *
     * @param  object|string  $class
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function resolve_policy($class)
    {
        return $this->container->make($class);
    }
    /**
     * Resolve the callback for a policy check.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $ability
     * @param  mixed  $policy
     * @return bool|callable
     */
    protected function resolve_policy_callback($user, $ability, array $arguments, $policy): false|\Closure
    {
        if (!is_callable([$policy, $this->format_ability_to_method($ability)])) {
            return false;
        }
        return function () use ($user, $ability, $arguments, $policy) {
            // This callback will be responsible for calling the policy's before method and
            // running this policy method if necessary. This is used to when objects are
            // mapped to policy objects in the user's configurations or on this class.
            $result = $this->call_policy_before($policy, $user, $ability, $arguments);
            // When we receive a non-null result from this before method, we will return it
            // as the "final" results. This will allow developers to override the checks
            // in this policy to return the result for all rules defined in the class.
            if (!is_null($result)) {
                return $result;
            }
            $method = $this->format_ability_to_method($ability);
            return $this->call_policy_method($policy, $method, $user, $arguments);
        };
    }
    /**
     * Call the "before" method on the given policy, if applicable.
     *
     * @param  mixed  $policy
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return mixed
     */
    protected function call_policy_before($policy, $user, $ability, $arguments)
    {
        if (!method_exists($policy, 'before')) {
            return;
        }
        if ($this->can_be_called_with_user($user, $policy, 'before')) {
            return $policy->before($user, $ability, ...$arguments);
        }
    }
    /**
     * Call the appropriate method on the given policy.
     *
     * @param  mixed  $policy
     * @param  string  $method
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @return mixed
     */
    protected function call_policy_method($policy, $method, $user, array $arguments)
    {
        // If this first argument is a string, that means they are passing a class name
        // to the policy. We will remove the first argument from this argument array
        // because this policy already knows what type of models it can authorize.
        if (isset($arguments[0]) && is_string($arguments[0])) {
            array_shift($arguments);
        }
        if (!is_callable([$policy, $method])) {
            return;
        }
        if ($this->can_be_called_with_user($user, $policy, $method)) {
            return $policy->{$method}($user, ...$arguments);
        }
    }
    /**
     * Format the policy ability into a method name.
     *
     * @param  string  $ability
     * @return string
     */
    protected function format_ability_to_method($ability)
    {
        return str_contains($ability, '-') ? Str::camel($ability) : $ability;
    }
    /**
     * Get a gate instance for the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|mixed  $user
     */
    public function for_user($user): static
    {
        return new static($this->container, fn() => $user, $this->abilities, $this->policies, $this->before_callbacks, $this->after_callbacks, $this->guess_policy_names_using_callback);
    }
    /**
     * Resolve the user from the user resolver.
     */
    protected function resolve_user(): mixed
    {
        return call_user_func($this->user_resolver);
    }
    /**
     * Get all of the defined abilities.
     *
     * @return array
     */
    public function abilities()
    {
        return $this->abilities;
    }
    /**
     * Get all of the defined policies.
     */
    public function policies(): array
    {
        return $this->policies;
    }
    /**
     * Set the default denial response for gates and policies.
     *
     * @return $this
     */
    public function default_denial_response(Response $response): static
    {
        $this->default_denial_response = $response;
        return $this;
    }
    /**
     * Set the container instance used by the gate.
     *
     * @return $this
     */
    public function set_container(Container $container): static
    {
        $this->container = $container;
        return $this;
    }
}