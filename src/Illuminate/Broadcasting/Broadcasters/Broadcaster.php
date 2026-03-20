<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting\Broadcasters;

use Closure;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Contracts\Broadcasting\Has_Broadcast_Channel;
use Illuminate\Contracts\Routing\Binding_Registrar;
use Illuminate\Contracts\Routing\Url_Routable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Reflector;
use ReflectionClass;
use ReflectionFunction;
use Symfony\Component\Http_Kernel\Exception\Access_Denied_Http_Exception;
abstract class Broadcaster implements Broadcaster_Contract
{
    /**
     * The callback to resolve the authenticated user information.
     *
     * @var \Closure|null
     */
    protected $authenticated_user_callback;
    /**
     * The registered channel authenticators.
     *
     * @var array
     */
    protected $channels = [];
    /**
     * The registered channel options.
     *
     * @var array
     */
    protected $channel_options = [];
    /**
     * The binding registrar instance.
     *
     * @var \Illuminate\Contracts\Routing\BindingRegistrar
     */
    protected $binding_registrar;
    /**
     * Resolve the authenticated user payload for the incoming connection request.
     *
     * See: https://pusher.com/docs/channels/library_auth_reference/auth-signatures/#user-authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|null
     */
    public function resolve_authenticated_user($request)
    {
        if ($this->authenticated_user_callback) {
            return $this->authenticated_user_callback->__invoke($request);
        }
    }
    /**
     * Register the user retrieval callback used to authenticate connections.
     *
     * See: https://pusher.com/docs/channels/library_auth_reference/auth-signatures/#user-authentication.
     */
    public function resolve_authenticated_user_using(Closure $callback): void
    {
        $this->authenticated_user_callback = $callback;
    }
    /**
     * Register a channel authenticator.
     *
     * @param  \Illuminate\Contracts\Broadcasting\HasBroadcastChannel|string  $channel
     * @param  callable|string  $callback
     * @param  array  $options
     * @return $this
     */
    public function channel($channel, $callback, $options = [])
    {
        if ($channel instanceof Has_Broadcast_Channel) {
            $channel = $channel->broadcast_channel_route();
        } elseif (is_string($channel) && class_exists($channel) && is_a($channel, Has_Broadcast_Channel::class, true)) {
            $channel = (new $channel())->broadcast_channel_route();
        }
        $this->channels[$channel] = $callback;
        $this->channel_options[$channel] = $options;
        return $this;
    }
    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $channel
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    protected function verify_user_can_access_channel($request, $channel)
    {
        foreach ($this->channels as $pattern => $callback) {
            if (!$this->channel_name_matches_pattern($channel, $pattern)) {
                continue;
            }
            $parameters = $this->extract_auth_parameters($pattern, $channel, $callback);
            $handler = $this->normalize_channel_handler_to_callable($callback);
            $result = $handler($this->retrieve_user($request, $channel), ...$parameters);
            if ($result === false) {
                throw new Access_Denied_Http_Exception();
            }
            if ($result) {
                return $this->valid_authentication_response($request, $result);
            }
        }
        throw new Access_Denied_Http_Exception();
    }
    /**
     * Extract the parameters from the given pattern and channel.
     *
     * @param  string  $pattern
     * @param  string  $channel
     * @param  callable|string  $callback
     * @return array
     */
    protected function extract_auth_parameters($pattern, $channel, $callback)
    {
        $callback_parameters = $this->extract_parameters($callback);
        return (new Collection($this->extract_channel_keys($pattern, $channel)))->reject(fn($value, $key): bool => is_numeric($key))->map(fn($value, $key): mixed => $this->resolve_binding($key, $value, $callback_parameters))->values()->all();
    }
    /**
     * Extracts the parameters out of what the user passed to handle the channel authentication.
     *
     * @param  callable|string  $callback
     * @return \ReflectionParameter[]
     *
     * @throws \Exception
     */
    protected function extract_parameters($callback)
    {
        if (is_callable($callback)) {
            return (new ReflectionFunction($callback))->get_parameters();
        }
        if (is_string($callback)) {
            return $this->extract_parameters_from_class($callback);
        }
        throw new Exception('Given channel handler is an unknown type.');
    }
    /**
     * Extracts the parameters out of a class channel's "join" method.
     *
     * @param  string  $callback
     * @return \ReflectionParameter[]
     *
     * @throws \Exception
     */
    protected function extract_parameters_from_class($callback)
    {
        $reflection = new ReflectionClass($callback);
        if (!$reflection->has_method('join')) {
            throw new Exception('Class based channel must define a "join" method.');
        }
        return $reflection->get_method('join')->get_parameters();
    }
    /**
     * Extract the channel keys from the incoming channel name.
     *
     * @param  string  $pattern
     * @param  string  $channel
     * @return array
     */
    protected function extract_channel_keys($pattern, $channel)
    {
        preg_match('/^' . preg_replace('/\{(.*?)\}/', '(?<$1>[^\.]+)', $pattern) . '/', $channel, $keys);
        return $keys;
    }
    /**
     * Resolve the given parameter binding.
     *
     * @param  string  $key
     * @param  string  $value
     * @param  array  $callbackParameters
     * @return mixed
     */
    protected function resolve_binding($key, $value, $callback_parameters)
    {
        $new_value = $this->resolve_explicit_binding_if_possible($key, $value);
        return $new_value === $value ? $this->resolve_implicit_binding_if_possible($key, $value, $callback_parameters) : $new_value;
    }
    /**
     * Resolve an explicit parameter binding if applicable.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function resolve_explicit_binding_if_possible($key, $value)
    {
        $binder = $this->binder();
        if ($binder && $binder->get_binding_callback($key)) {
            return call_user_func($binder->get_binding_callback($key), $value);
        }
        return $value;
    }
    /**
     * Resolve an implicit parameter binding if applicable.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $callbackParameters
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    protected function resolve_implicit_binding_if_possible($key, $value, $callback_parameters)
    {
        foreach ($callback_parameters as $parameter) {
            if (!$this->is_implicitly_bindable($key, $parameter)) {
                continue;
            }
            $class_name = Reflector::get_parameter_class_name($parameter);
            if (is_null($model = (new $class_name())->resolve_route_binding($value))) {
                throw new Access_Denied_Http_Exception();
            }
            return $model;
        }
        return $value;
    }
    /**
     * Determine if a given key and parameter is implicitly bindable.
     *
     * @param  string  $key
     * @param  \ReflectionParameter  $parameter
     * @return bool
     */
    protected function is_implicitly_bindable($key, $parameter)
    {
        return $parameter->get_name() === $key && Reflector::is_parameter_subclass_of($parameter, Url_Routable::class);
    }
    /**
     * Format the channel array into an array of strings.
     *
     * @return array
     */
    protected function format_channels(array $channels)
    {
        return array_map(fn($channel): string => (string) $channel, $channels);
    }
    /**
     * Get the model binding registrar instance.
     *
     * @return \Illuminate\Contracts\Routing\BindingRegistrar
     */
    protected function binder()
    {
        if (!$this->binding_registrar) {
            $this->binding_registrar = Container::get_instance()->bound(Binding_Registrar::class) ? Container::get_instance()->make(Binding_Registrar::class) : null;
        }
        return $this->binding_registrar;
    }
    /**
     * Normalize the given callback into a callable.
     *
     * @param  mixed  $callback
     * @return callable
     */
    protected function normalize_channel_handler_to_callable($callback)
    {
        return is_callable($callback) ? $callback : fn(...$args) => Container::get_instance()->make($callback)->join(...$args);
    }
    /**
     * Retrieve the authenticated user using the configured guard (if any).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $channel
     * @return mixed
     */
    protected function retrieve_user($request, $channel)
    {
        $options = $this->retrieve_channel_options($channel);
        $guards = $options['guards'] ?? null;
        if (is_null($guards)) {
            return $request->user();
        }
        foreach (Arr::wrap($guards) as $guard) {
            if ($user = $request->user($guard)) {
                return $user;
            }
        }
    }
    /**
     * Retrieve options for a certain channel.
     *
     * @param  string  $channel
     * @return array
     */
    protected function retrieve_channel_options($channel)
    {
        foreach ($this->channel_options as $pattern => $options) {
            if (!$this->channel_name_matches_pattern($channel, $pattern)) {
                continue;
            }
            return $options;
        }
        return [];
    }
    /**
     * Check if the channel name from the request matches a pattern from registered channels.
     *
     * @param  string  $channel
     * @param  string  $pattern
     * @return bool
     */
    protected function channel_name_matches_pattern($channel, $pattern)
    {
        $pattern = str_replace('.', '\.', $pattern);
        return preg_match('/^' . preg_replace('/\{(.*?)\}/', '([^\.]+)', $pattern) . '$/', $channel);
    }
    /**
     * Get all of the registered channels.
     *
     * @return \Illuminate\Support\Collection
     */
    public function get_channels()
    {
        return new Collection($this->channels);
    }
}