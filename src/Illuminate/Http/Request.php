<?php

declare (strict_types=1);
namespace Illuminate\Http;

use ArrayAccess;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Session\Symfony_Session_Decorator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Uri;
use RuntimeException;
use Symfony\Component\Http_Foundation\Exception\Session_Not_Found_Exception;
use Symfony\Component\Http_Foundation\Input_Bag;
use Symfony\Component\Http_Foundation\Request as SymfonyRequest;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
/**
 * @method array validate(array $rules, ...$params)
 * @method array validateWithBag(string $errorBag, array $rules, ...$params)
 * @method bool hasValidSignature(bool $absolute = true)
 * @method bool hasValidRelativeSignature()
 * @method bool hasValidSignatureWhileIgnoring($ignoreQuery = [], $absolute = true)
 * @method bool hasValidRelativeSignatureWhileIgnoring($ignoreQuery = [])
 */
class Request extends Symfony_Request implements Arrayable, ArrayAccess
{
    use Concerns\Can_Be_Precognitive;
    use Concerns\Interacts_With_Content_Types;
    use Concerns\Interacts_With_Flash_Data;
    use Concerns\Interacts_With_Input;
    use Conditionable;
    use Macroable;
    /**
     * The decoded JSON content for the request.
     *
     * @var \Symfony\Component\HttpFoundation\InputBag|null
     */
    protected $json;
    /**
     * All of the converted files for the request.
     *
     * @var array<int, \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]>
     */
    protected $converted_files;
    /**
     * The user resolver callback.
     *
     * @var \Closure
     */
    protected $user_resolver;
    /**
     * The route resolver callback.
     *
     * @var \Closure
     */
    protected $route_resolver;
    /**
     * The cached "Accept" header value.
     *
     * @var string|null
     */
    protected $cached_accept_header;
    /**
     * Create a new Illuminate HTTP request from server variables.
     *
     * @return static
     */
    public static function capture()
    {
        static::enable_http_method_parameter_override();
        return static::create_from_base(Symfony_Request::create_from_globals());
    }
    /**
     * Return the Request instance.
     *
     * @return $this
     */
    public function instance()
    {
        return $this;
    }
    /**
     * Get the request method.
     *
     * @return string
     */
    public function method()
    {
        return $this->get_method();
    }
    /**
     * Get a URI instance for the request.
     *
     * @return \Illuminate\Support\Uri
     */
    public function uri()
    {
        return Uri::of($this->full_url());
    }
    /**
     * Get the root URL for the application.
     *
     * @return string
     */
    public function root()
    {
        return rtrim($this->get_scheme_and_http_host() . $this->get_base_url(), '/');
    }
    /**
     * Get the URL (no query string) for the request.
     *
     * @return string
     */
    public function url()
    {
        return rtrim(preg_replace('/\?.*/', '', $this->get_uri()), '/');
    }
    /**
     * Get the full URL for the request.
     *
     * @return string
     */
    public function full_url()
    {
        $query = $this->get_query_string();
        $question = $this->get_base_url() . $this->get_path_info() === '/' ? '/?' : '?';
        return $query ? $this->url() . $question . $query : $this->url();
    }
    /**
     * Get the full URL for the request with the added query string parameters.
     *
     * @return string
     */
    public function full_url_with_query(array $query)
    {
        $question = $this->get_base_url() . $this->get_path_info() === '/' ? '/?' : '?';
        return count($this->query()) > 0 ? $this->url() . $question . Arr::query(array_merge($this->query(), $query)) : $this->full_url() . $question . Arr::query($query);
    }
    /**
     * Get the full URL for the request without the given query string parameters.
     *
     * @param  array|string  $keys
     * @return string
     */
    public function full_url_without_query($keys)
    {
        $query = Arr::except($this->query(), $keys);
        $question = $this->get_base_url() . $this->get_path_info() === '/' ? '/?' : '?';
        return count($query) > 0 ? $this->url() . $question . Arr::query($query) : $this->url();
    }
    /**
     * Get the current path info for the request.
     *
     * @return string
     */
    public function path()
    {
        $pattern = trim($this->get_path_info(), '/');
        return $pattern === '' ? '/' : $pattern;
    }
    /**
     * Get the current decoded path info for the request.
     *
     * @return string
     */
    public function decoded_path()
    {
        return rawurldecode($this->path());
    }
    /**
     * Get a segment from the URI (1 based index).
     *
     * @param  int  $index
     * @param  string|null  $default
     * @return string|null
     */
    public function segment($index, $default = null)
    {
        return Arr::get($this->segments(), $index - 1, $default);
    }
    /**
     * Get all of the segments for the request path.
     *
     * @return array
     */
    public function segments()
    {
        $segments = explode('/', $this->decoded_path());
        return array_values(array_filter($segments, fn($value): bool => $value !== ''));
    }
    /**
     * Determine if the current request URI matches a pattern.
     *
     * @param  mixed  ...$patterns
     * @return bool
     */
    public function is(...$patterns)
    {
        return (new Collection($patterns))->contains(fn($pattern): bool => Str::is($pattern, $this->decoded_path()));
    }
    /**
     * Determine if the route name matches a given pattern.
     *
     * @param  mixed  ...$patterns
     * @return bool
     */
    public function route_is(...$patterns)
    {
        return $this->route() && $this->route()->named(...$patterns);
    }
    /**
     * Determine if the current request URL and query string match a pattern.
     *
     * @param  mixed  ...$patterns
     * @return bool
     */
    public function full_url_is(...$patterns)
    {
        return (new Collection($patterns))->contains(fn($pattern): bool => Str::is($pattern, $this->full_url()));
    }
    /**
     * Get the host name.
     *
     * @return string
     */
    public function host()
    {
        return $this->get_host();
    }
    /**
     * Get the HTTP host being requested.
     *
     * @return string
     */
    public function http_host()
    {
        return $this->get_http_host();
    }
    /**
     * Get the scheme and HTTP host.
     *
     * @return string
     */
    public function scheme_and_http_host()
    {
        return $this->get_scheme_and_http_host();
    }
    /**
     * Determine if the request is the result of an AJAX call.
     *
     * @return bool
     */
    public function ajax()
    {
        return $this->is_xml_http_request();
    }
    /**
     * Determine if the request is the result of a PJAX call.
     *
     * @return bool
     */
    public function pjax()
    {
        return $this->headers->get('X-PJAX') == true;
    }
    /**
     * Determine if the request is the result of a prefetch call.
     *
     * @return bool
     */
    public function prefetch()
    {
        return strcasecmp($this->server->get('HTTP_X_MOZ') ?? '', 'prefetch') === 0 || strcasecmp($this->headers->get('Purpose') ?? '', 'prefetch') === 0 || strcasecmp($this->headers->get('Sec-Purpose') ?? '', 'prefetch') === 0;
    }
    /**
     * Determine if the request is over HTTPS.
     *
     * @return bool
     */
    public function secure()
    {
        return $this->is_secure();
    }
    /**
     * Get the client IP address.
     *
     * @return string|null
     */
    public function ip()
    {
        return $this->get_client_ip();
    }
    /**
     * Get the client IP addresses.
     *
     * @return array
     */
    public function ips()
    {
        return $this->get_client_ips();
    }
    /**
     * Get the client user agent.
     *
     * @return string|null
     */
    public function user_agent()
    {
        return $this->headers->get('User-Agent');
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function get_acceptable_content_types(): array
    {
        $current_accept_header = $this->headers->get('Accept');
        if ($this->cached_accept_header !== $current_accept_header) {
            // Flush acceptable content types so Symfony re-calculates them...
            $this->acceptable_content_types = null;
            $this->cached_accept_header = $current_accept_header;
        }
        return parent::get_acceptable_content_types();
    }
    /**
     * Merge new input into the current request's input array.
     *
     * @return $this
     */
    public function merge(array $input)
    {
        return tap($this, function (Request $request) use ($input): void {
            $request->get_input_source()->replace((new Collection($input))->reduce(fn(array $request_input, $value, $key) => data_set($request_input, $key, $value), $this->get_input_source()->all()));
        });
    }
    /**
     * Merge new input into the request's input, but only when that key is missing from the request.
     *
     * @return $this
     */
    public function merge_if_missing(array $input)
    {
        return $this->merge((new Collection($input))->filter(fn($value, $key): bool => $this->missing($key))->to_array());
    }
    /**
     * Replace the input values for the current request.
     *
     * @return $this
     */
    public function replace(array $input)
    {
        $this->get_input_source()->replace($input);
        return $this;
    }
    /**
     * This method belongs to Symfony HttpFoundation and is not usually needed when using Laravel.
     *
     * Instead, you may use the "input" method.
     *
     *
     * @deprecated use ->input() instead
     */
    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return parent::get($key, $default);
    }
    /**
     * Get the JSON payload for the request.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? \Symfony\Component\HttpFoundation\InputBag : mixed)
     */
    public function json($key = null, $default = null)
    {
        if (!isset($this->json)) {
            $this->json = new Input_Bag((array) json_decode($this->get_content() ?: '[]', true));
        }
        if (is_null($key)) {
            return $this->json;
        }
        return data_get($this->json->all(), $key, $default);
    }
    /**
     * Get the input source for the request.
     *
     * @return \Symfony\Component\HttpFoundation\InputBag
     */
    protected function get_input_source()
    {
        if ($this->is_json()) {
            return $this->json();
        }
        return in_array($this->get_real_method(), ['GET', 'HEAD']) ? $this->query : $this->request;
    }
    /**
     * Create a new request instance from the given Laravel request.
     *
     * @param  \Illuminate\Http\Request|null  $to
     * @return static
     */
    public static function create_from(self $from, $to = null)
    {
        $request = $to ?: new static();
        $files = array_filter($from->files->all());
        $request->initialize($from->query->all(), $from->request->all(), $from->attributes->all(), $from->cookies->all(), $files, $from->server->all(), $from->get_content());
        $request->headers->replace($from->headers->all());
        $request->set_request_locale($from->get_locale());
        $request->set_default_request_locale($from->get_default_locale());
        $request->set_json($from->json());
        if ($from->has_session() && $session = $from->session()) {
            $request->set_laravel_session($session);
        }
        $request->set_user_resolver($from->get_user_resolver());
        $request->set_route_resolver($from->get_route_resolver());
        return $request;
    }
    /**
     * Create an Illuminate request from a Symfony instance.
     *
     * @return static
     */
    public static function create_from_base(Symfony_Request $request)
    {
        $new_request = new static($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(), (new static())->filter_files($request->files->all()) ?? [], $request->server->all());
        $new_request->headers->replace($request->headers->all());
        $new_request->content = $request->content;
        if ($new_request->is_json()) {
            $new_request->request = $new_request->json();
        }
        return $new_request;
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function duplicate(?array $query = null, ?array $request = null, ?array $attributes = null, ?array $cookies = null, ?array $files = null, ?array $server = null): static
    {
        return parent::duplicate($query, $request, $attributes, $cookies, $this->filter_files($files), $server);
    }
    /**
     * Filter the given array of files, removing any empty values.
     *
     * @param  mixed  $files
     * @return mixed
     */
    protected function filter_files($files)
    {
        if (!$files) {
            return;
        }
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $files[$key] = $this->filter_files($files[$key]);
            }
            if (empty($files[$key])) {
                unset($files[$key]);
            }
        }
        return $files;
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function has_session(bool $skip_if_uninitialized = false): bool
    {
        return $this->session instanceof Symfony_Session_Decorator;
    }
    /**
     * {@inheritdoc}
     *
     * @throws \Symfony\Component\HttpFoundation\Exception\SessionNotFoundException
     */
    #[\Override]
    public function get_session(): Session_Interface
    {
        return $this->has_session() ? $this->session : throw new Session_Not_Found_Exception();
    }
    /**
     * Get the session associated with the request.
     *
     * @return \Illuminate\Contracts\Session\Session
     *
     * @throws \RuntimeException
     */
    public function session()
    {
        if (!$this->has_session()) {
            throw new RuntimeException('Session store not set on request.');
        }
        return $this->session->store;
    }
    /**
     * Set the session instance on the request.
     *
     * @param  \Illuminate\Contracts\Session\Session  $session
     */
    public function set_laravel_session($session): void
    {
        $this->session = new Symfony_Session_Decorator($session);
    }
    /**
     * Set the locale for the request instance.
     */
    public function set_request_locale(string $locale): void
    {
        $this->locale = $locale;
    }
    /**
     * Set the default locale for the request instance.
     */
    public function set_default_request_locale(string $locale): void
    {
        $this->default_locale = $locale;
    }
    /**
     * Get the user making the request.
     *
     * @param  string|null  $guard
     * @return mixed
     */
    public function user($guard = null)
    {
        return call_user_func($this->get_user_resolver(), $guard);
    }
    /**
     * Get the route handling the request.
     *
     * @param  string|null  $param
     * @param  mixed  $default
     * @return ($param is null ? \Illuminate\Routing\Route : object|string|null)
     */
    public function route($param = null, $default = null)
    {
        $route = call_user_func($this->get_route_resolver());
        if (is_null($route) || is_null($param)) {
            return $route;
        }
        return $route->parameter($param, $default);
    }
    /**
     * Get a unique fingerprint for the request / route / IP address.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function fingerprint()
    {
        if (!$route = $this->route()) {
            throw new RuntimeException('Unable to generate fingerprint. Route unavailable.');
        }
        return sha1(implode('|', array_merge($route->methods(), [$route->get_domain(), $route->uri(), $this->ip()])));
    }
    /**
     * Set the JSON payload for the request.
     *
     * @param  \Symfony\Component\HttpFoundation\InputBag  $json
     * @return $this
     */
    public function set_json($json)
    {
        $this->json = $json;
        return $this;
    }
    /**
     * Get the user resolver callback.
     *
     * @return \Closure
     */
    public function get_user_resolver()
    {
        return $this->user_resolver ?: function (): void {
        };
    }
    /**
     * Set the user resolver callback.
     *
     * @return $this
     */
    public function set_user_resolver(Closure $callback)
    {
        $this->user_resolver = $callback;
        return $this;
    }
    /**
     * Get the route resolver callback.
     *
     * @return \Closure
     */
    public function get_route_resolver()
    {
        return $this->route_resolver ?: function (): void {
        };
    }
    /**
     * Set the route resolver callback.
     *
     * @return $this
     */
    public function set_route_resolver(Closure $callback)
    {
        $this->route_resolver = $callback;
        return $this;
    }
    /**
     * Get all of the input and files for the request.
     */
    public function to_array(): array
    {
        return $this->all();
    }
    /**
     * Determine if the given offset exists.
     *
     * @param  string  $offset
     */
    public function offsetExists($offset): bool
    {
        $route = $this->route();
        return Arr::has($this->all() + ($route ? $route->parameters() : []), $offset);
    }
    /**
     * Get the value at the given offset.
     *
     * @param  string  $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->__get($offset);
    }
    /**
     * Set the value at the given offset.
     *
     * @param  string  $offset
     * @param  mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->get_input_source()->set($offset, $value);
    }
    /**
     * Remove the value at the given offset.
     *
     * @param  string  $offset
     */
    public function offsetUnset($offset): void
    {
        $this->get_input_source()->remove($offset);
    }
    /**
     * Check if an input element is set on the request.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return !is_null($this->__get($key));
    }
    /**
     * Get an input element from the request.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return Arr::get($this->all(), $key, fn() => $this->route($key));
    }
}