<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

use Closure;
use Exception;
use Guzzle_Http\Client;
use Guzzle_Http\Cookie\Cookie_Jar;
use Guzzle_Http\Exception\Connect_Exception;
use Guzzle_Http\Exception\Request_Exception;
use Guzzle_Http\Exception\Transfer_Exception;
use Guzzle_Http\Handler_Stack;
use Guzzle_Http\Middleware;
use Guzzle_Http\Promise\Each_Promise;
use Guzzle_Http\Promise\Promise_Interface;
use Guzzle_Http\Uri_Template\Uri_Template;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\Events\Connection_Failed;
use Illuminate\Http\Client\Events\Request_Sending;
use Illuminate\Http\Client\Events\Response_Received;
use Illuminate\Http\Client\Promises\Fluent_Promise;
use Illuminate\Http\Client\Promises\Lazy_Promise;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use JsonSerializable;
use Psr\Http\Message\Message_Interface;
use Psr\Http\Message\Request_Interface;
use Symfony\Component\Var_Dumper\Var_Dumper;
use Throwable;
/**
 * @template TAsync of bool = false
 */
class Pending_Request
{
    use Conditionable;
    use Macroable;
    /**
     * The Guzzle client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;
    /**
     * The Guzzle HTTP handler.
     *
     * @var callable
     */
    protected $handler;
    /**
     * The base URL for the request.
     *
     * @var string
     */
    protected $base_url = '';
    /**
     * The parameters that can be substituted into the URL.
     *
     * @var array
     */
    protected $url_parameters = [];
    /**
     * The request body format.
     *
     * @var string
     */
    protected $body_format;
    /**
     * The raw body for the request.
     *
     * @var \Psr\Http\Message\StreamInterface|string
     */
    protected $pending_body;
    /**
     * The pending files for the request.
     *
     * @var array
     */
    protected $pending_files = [];
    /**
     * The request cookies.
     *
     * @var array
     */
    protected $cookies;
    /**
     * The transfer stats for the request.
     *
     * @var \GuzzleHttp\TransferStats
     */
    protected $transfer_stats;
    /**
     * The request options.
     */
    protected array $options;
    /**
     * A callback to run when throwing if a server or client error occurs.
     *
     * @var \Closure
     */
    protected $throw_callback;
    /**
     * A callback to check if an exception should be thrown when a server or client error occurs.
     *
     * @var \Closure
     */
    protected $throw_if_callback;
    /**
     * The number of times to try the request.
     *
     * @var int
     */
    protected $tries = 1;
    /**
     * The number of milliseconds to wait between retries.
     *
     * @var (Closure(int, mixed): int)|int
     */
    protected $retry_delay = 100;
    /**
     * Whether to throw an exception when all retries fail.
     *
     * @var bool
     */
    protected $retry_throw = true;
    /**
     * The callback that will determine if the request should be retried.
     *
     * @var (callable(\Throwable, static, string|null): bool)|null
     */
    protected $retry_when_callback;
    /**
     * The callbacks that should execute before the request is sent.
     */
    protected \Illuminate\Support\Collection $before_sending_callbacks;
    /**
     * The callbacks that should execute after the Laravel Response is built.
     *
     * @var \Illuminate\Support\Collection<int, (callable(\Illuminate\Http\Client\Response): \Illuminate\Http\Client\Response|null)>
     */
    protected $after_response_callbacks;
    /**
     * The stub callables that will handle requests.
     *
     * @var \Illuminate\Support\Collection|null
     */
    protected $stub_callbacks;
    /**
     * Indicates that an exception should be thrown if any request is not faked.
     *
     * @var bool
     */
    protected $prevent_stray_requests = false;
    /**
     * A list of URL patterns that are allowed to bypass the stray request guard.
     *
     * @var array<int, string>
     */
    protected $allowed_stray_request_urls = [];
    /**
     * The middleware callables added by users that will handle requests.
     */
    protected \Illuminate\Support\Collection $middleware;
    /**
     * Whether the requests should be asynchronous.
     *
     * @var TAsync
     */
    protected $async = false;
    /**
     * The attributes to track with the request.
     *
     * @var array<array-key, mixed>
     */
    protected $attributes = [];
    /**
     * The pending request promise.
     *
     * @var \GuzzleHttp\Promise\PromiseInterface
     */
    protected $promise;
    /**
     * The sent request object, if a request has been made.
     *
     * @var \Illuminate\Http\Client\Request|null
     */
    protected $request;
    /**
     * The Guzzle request options that are mergeable via array_merge_recursive.
     *
     * @var array
     */
    protected $mergeable_options = ['cookies', 'form_params', 'headers', 'json', 'multipart', 'query'];
    /**
     * The length at which request exceptions will be truncated.
     *
     * @var int<1, max>|false|null
     */
    protected $truncate_exceptions_at;
    /**
     * Create a new HTTP Client instance.
     *
     * @param  array  $middleware
     */
    public function __construct(
        /**
         * The factory instance.
         */
        protected ?\Illuminate\Http\Client\Factory $factory = null,
        $middleware = []
    )
    {
        $this->middleware = new Collection($middleware);
        $this->as_json();
        $this->options = ['connect_timeout' => 10, 'crypto_method' => Stream_crypto_method_tl_Sv1_2_client, 'http_errors' => false, 'timeout' => 30];
        $this->before_sending_callbacks = new Collection([function (Request $request, array $options, Pending_Request $pending_request): void {
            $pending_request->request = $request;
            $pending_request->cookies = $options['cookies'];
            $pending_request->dispatch_request_sending_event();
        }]);
        $this->after_response_callbacks = new Collection();
    }
    /**
     * Set the base URL for the pending request.
     *
     * @return $this
     */
    public function base_url(string $url): static
    {
        $this->base_url = $url;
        return $this;
    }
    /**
     * Attach a raw body to the request.
     *
     * @param  \Psr\Http\Message\StreamInterface|string  $content
     * @return $this
     */
    public function with_body($content, string $content_type = 'application/json'): static
    {
        $this->body_format('body');
        $this->pending_body = $content;
        $this->content_type($content_type);
        return $this;
    }
    /**
     * Indicate the request contains JSON.
     *
     * @return $this
     */
    public function as_json(): static
    {
        return $this->body_format('json')->content_type('application/json');
    }
    /**
     * Indicate the request contains form parameters.
     *
     * @return $this
     */
    public function as_form(): static
    {
        return $this->body_format('form_params')->content_type('application/x-www-form-urlencoded');
    }
    /**
     * Attach a file to the request.
     *
     * @param  string|array  $name
     * @param  string|resource  $contents
     * @param  string|null  $filename
     * @return $this
     */
    public function attach($name, $contents = '', $filename = null, array $headers = []): static
    {
        if (is_array($name)) {
            foreach ($name as $file) {
                $this->attach(...$file);
            }
            return $this;
        }
        $this->as_multipart();
        $this->pending_files[] = array_filter(['name' => $name, 'contents' => $contents, 'headers' => $headers, 'filename' => $filename]);
        return $this;
    }
    /**
     * Indicate the request is a multi-part form request.
     *
     * @return $this
     */
    public function as_multipart()
    {
        return $this->body_format('multipart');
    }
    /**
     * Specify the body format of the request.
     *
     * @return $this
     */
    public function body_format(string $format)
    {
        return tap($this, function () use ($format): void {
            $this->body_format = $format;
        });
    }
    /**
     * Set the given query parameters in the request URI.
     *
     * @return $this
     */
    public function with_query_parameters(array $parameters)
    {
        return tap($this, function () use ($parameters): void {
            $this->options = array_merge_recursive($this->options, ['query' => $parameters]);
        });
    }
    /**
     * Specify the request's content type.
     *
     * @return $this
     */
    public function content_type(string $content_type): static
    {
        $this->options['headers']['Content-Type'] = $content_type;
        return $this;
    }
    /**
     * Indicate that JSON should be returned by the server.
     *
     * @return $this
     */
    public function accept_json()
    {
        return $this->accept('application/json');
    }
    /**
     * Indicate the type of content that should be returned by the server.
     *
     * @param  string  $contentType
     * @return $this
     */
    public function accept($content_type)
    {
        return $this->with_headers(['Accept' => $content_type]);
    }
    /**
     * Add the given headers to the request.
     *
     * @return $this
     */
    public function with_headers(array $headers)
    {
        return tap($this, function () use ($headers): void {
            $this->options = array_merge_recursive($this->options, ['headers' => $headers]);
        });
    }
    /**
     * Add the given header to the request.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @return $this
     */
    public function with_header($name, $value)
    {
        return $this->with_headers([$name => $value]);
    }
    /**
     * Replace the given headers on the request.
     *
     * @return $this
     */
    public function replace_headers(array $headers): static
    {
        $this->options['headers'] = array_merge($this->options['headers'] ?? [], $headers);
        return $this;
    }
    /**
     * Specify the basic authentication username and password for the request.
     *
     * @return $this
     */
    public function with_basic_auth(string $username, string $password)
    {
        return tap($this, function () use ($username, $password): void {
            $this->options['auth'] = [$username, $password];
        });
    }
    /**
     * Specify the digest authentication username and password for the request.
     *
     * @param  string  $username
     * @param  string  $password
     * @return $this
     */
    public function with_digest_auth($username, $password)
    {
        return tap($this, function () use ($username, $password): void {
            $this->options['auth'] = [$username, $password, 'digest'];
        });
    }
    /**
     * Specify the NTLM authentication username and password for the request.
     *
     * @param  string  $username
     * @param  string  $password
     * @return $this
     */
    public function with_ntlm_auth($username, $password)
    {
        return tap($this, function () use ($username, $password): void {
            $this->options['auth'] = [$username, $password, 'ntlm'];
        });
    }
    /**
     * Specify an authorization token for the request.
     *
     * @param  string  $token
     * @param  string  $type
     * @return $this
     */
    public function with_token($token, $type = 'Bearer')
    {
        return tap($this, function () use ($token, $type): void {
            $this->options['headers']['Authorization'] = trim($type . ' ' . $token);
        });
    }
    /**
     * Specify the user agent for the request.
     *
     * @param  string|bool  $userAgent
     * @return $this
     */
    public function with_user_agent($user_agent)
    {
        return tap($this, function () use ($user_agent): void {
            $this->options['headers']['User-Agent'] = trim($user_agent);
        });
    }
    /**
     * Specify the URL parameters that can be substituted into the request URL.
     *
     * @return $this
     */
    public function with_url_parameters(array $parameters = [])
    {
        return tap($this, function () use ($parameters): void {
            $this->url_parameters = array_merge($this->url_parameters, $parameters);
        });
    }
    /**
     * Specify the cookies that should be included with the request.
     *
     * @return $this
     */
    public function with_cookies(array $cookies, string $domain)
    {
        return tap($this, function () use ($cookies, $domain): void {
            $this->options = array_merge_recursive($this->options, ['cookies' => Cookie_Jar::from_array($cookies, $domain)]);
        });
    }
    /**
     * Specify the maximum number of redirects to allow.
     *
     * @return $this
     */
    public function max_redirects(int $max)
    {
        return tap($this, function () use ($max): void {
            $this->options['allow_redirects']['max'] = $max;
        });
    }
    /**
     * Indicate that redirects should not be followed.
     *
     * @return $this
     */
    public function without_redirecting()
    {
        return tap($this, function (): void {
            $this->options['allow_redirects'] = false;
        });
    }
    /**
     * Indicate that TLS certificates should not be verified.
     *
     * @return $this
     */
    public function without_verifying()
    {
        return tap($this, function (): void {
            $this->options['verify'] = false;
        });
    }
    /**
     * Specify the path where the body of the response should be stored.
     *
     * @param  string|resource  $to
     * @return $this
     */
    public function sink($to)
    {
        return tap($this, function () use ($to): void {
            $this->options['sink'] = $to;
        });
    }
    /**
     * Specify the timeout (in seconds) for the request.
     *
     * @return $this
     */
    public function timeout(int|float $seconds)
    {
        return tap($this, function () use ($seconds): void {
            $this->options['timeout'] = $seconds;
        });
    }
    /**
     * Specify the connect timeout (in seconds) for the request.
     *
     * @return $this
     */
    public function connect_timeout(int|float $seconds)
    {
        return tap($this, function () use ($seconds): void {
            $this->options['connect_timeout'] = $seconds;
        });
    }
    /**
     * Specify the number of times the request should be attempted.
     *
     * @param  (Closure(int, mixed): int)|int  $sleepMilliseconds
     * @param  (callable(\Throwable, static, string|null): bool)|null  $when
     * @return $this
     */
    public function retry(array|int $times, Closure|int $sleep_milliseconds = 0, ?callable $when = null, bool $throw = true): static
    {
        $this->tries = $times;
        $this->retry_delay = $sleep_milliseconds;
        $this->retry_when_callback = $when;
        $this->retry_throw = $throw;
        return $this;
    }
    /**
     * Replace the specified options on the request.
     *
     * @return $this
     */
    public function with_options(array $options)
    {
        return tap($this, function () use ($options): void {
            $this->options = array_replace_recursive(array_merge_recursive($this->options, Arr::only($options, $this->mergeable_options)), $options);
        });
    }
    /**
     * Add new middleware the client handler stack.
     *
     * @return $this
     */
    public function with_middleware(callable $middleware): static
    {
        $this->middleware->push($middleware);
        return $this;
    }
    /**
     * Add new request middleware the client handler stack.
     *
     * @return $this
     */
    public function with_request_middleware(callable $middleware): static
    {
        $this->middleware->push(Middleware::map_request($middleware));
        return $this;
    }
    /**
     * Add new response middleware the client handler stack.
     *
     * @return $this
     */
    public function with_response_middleware(callable $middleware): static
    {
        $this->middleware->push(Middleware::map_response($middleware));
        return $this;
    }
    /**
     * Set arbitrary attributes to store with the request.
     *
     * @param  array<array-key, mixed>  $attributes
     * @return $this
     */
    public function with_attributes($attributes): static
    {
        $this->attributes = array_merge_recursive($this->attributes, $attributes);
        return $this;
    }
    /**
     * Add a new "before sending" callback to the request.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function before_sending($callback)
    {
        return tap($this, function () use ($callback): void {
            $this->before_sending_callbacks[] = $callback;
        });
    }
    /**
     * Add a new callback to execute after the response is built.
     *
     * @param  (callable(\Illuminate\Http\Client\Response): \Illuminate\Http\Client\Response|null)  $callback
     * @return $this
     */
    public function after_response(callable $callback): static
    {
        $this->after_response_callbacks[] = $callback;
        return $this;
    }
    /**
     * Throw an exception if a server or client error occurs.
     *
     * @return $this
     */
    public function throw(?callable $callback = null): static
    {
        $this->throw_callback = $callback ?: fn(): null => null;
        return $this;
    }
    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to true.
     *
     * @param  callable|bool  $condition
     * @return $this
     */
    public function throw_if($condition): static
    {
        if (is_callable($condition)) {
            $this->throw_if_callback = $condition;
        }
        return $condition ? $this->throw(func_get_args()[1] ?? null) : $this;
    }
    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to false.
     *
     * @param  callable|bool  $condition
     * @return $this
     */
    public function throw_unless($condition)
    {
        return $this->throw_if(!$condition);
    }
    /**
     * Dump the request before sending.
     *
     * @return $this
     */
    public function dump()
    {
        $values = func_get_args();
        return $this->before_sending(function (Request $request, array $options) use ($values): void {
            foreach (array_merge($values, [$request, $options]) as $value) {
                Var_Dumper::dump($value);
            }
        });
    }
    /**
     * Dump the request before sending and end the script.
     *
     * @return $this
     */
    public function dd()
    {
        $values = func_get_args();
        return $this->before_sending(function (Request $request, array $options) use ($values): void {
            foreach (array_merge($values, [$request, $options]) as $value) {
                Var_Dumper::dump($value);
            }
            exit(1);
        });
    }
    /**
     * Issue a GET request to the given URL.
     *
     * @param  array|string|null  $query
     * @return \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
     *
     * @phpstan-return (TAsync is false ?  \Illuminate\Http\Client\Response : \GuzzleHttp\Promise\PromiseInterface)
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function get(string $url, $query = null)
    {
        return $this->send('GET', $url, func_num_args() === 1 ? [] : ['query' => $query]);
    }
    /**
     * Issue a HEAD request to the given URL.
     *
     * @param  array|string|null  $query
     * @return \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
     *
     * @phpstan-return (TAsync is false ?  \Illuminate\Http\Client\Response : \GuzzleHttp\Promise\PromiseInterface)
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function head(string $url, $query = null)
    {
        return $this->send('HEAD', $url, func_num_args() === 1 ? [] : ['query' => $query]);
    }
    /**
     * Issue a POST request to the given URL.
     *
     * @param  array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable  $data
     * @return \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
     *
     * @phpstan-return (TAsync is false ?  \Illuminate\Http\Client\Response : \GuzzleHttp\Promise\PromiseInterface)
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function post(string $url, $data = [])
    {
        return $this->send('POST', $url, [$this->body_format => $data]);
    }
    /**
     * Issue a PATCH request to the given URL.
     *
     * @param  array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable  $data
     * @return \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
     *
     * @phpstan-return (TAsync is false ?  \Illuminate\Http\Client\Response : \GuzzleHttp\Promise\PromiseInterface)
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function patch(string $url, $data = [])
    {
        return $this->send('PATCH', $url, [$this->body_format => $data]);
    }
    /**
     * Issue a PUT request to the given URL.
     *
     * @param  array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable  $data
     * @return \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
     *
     * @phpstan-return (TAsync is false ?  \Illuminate\Http\Client\Response : \GuzzleHttp\Promise\PromiseInterface)
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function put(string $url, $data = [])
    {
        return $this->send('PUT', $url, [$this->body_format => $data]);
    }
    /**
     * Issue a DELETE request to the given URL.
     *
     * @param  array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable  $data
     * @return \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
     *
     * @phpstan-return (TAsync is false ?  \Illuminate\Http\Client\Response : \GuzzleHttp\Promise\PromiseInterface)
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function delete(string $url, $data = [])
    {
        return $this->send('DELETE', $url, empty($data) ? [] : [$this->body_format => $data]);
    }
    /**
     * Send a pool of asynchronous requests concurrently.
     *
     * @param  (callable(\Illuminate\Http\Client\Pool): mixed)  $callback
     * @param  non-negative-int|null  $concurrency
     * @return array<array-key, \Illuminate\Http\Client\Response|\Throwable>
     */
    public function pool(callable $callback, ?int $concurrency = null): array
    {
        $results = [];
        $requests = tap(new Pool($this->factory), $callback)->get_requests();
        if ($concurrency === null) {
            (new Collection($requests))->each(static function ($item): void {
                if ($item instanceof static) {
                    $item = $item->get_promise();
                }
                if ($item instanceof Lazy_Promise) {
                    $item->build_promise();
                }
            });
            foreach ($requests as $key => $item) {
                $results[$key] = $item instanceof static ? $item->get_promise()->wait() : $item->wait();
            }
            return $results;
        }
        $concurrency = $concurrency === 0 ? count($requests) : $concurrency;
        $promise_generator = static function () use ($requests) {
            foreach ($requests as $key => $item) {
                $promise = $item instanceof static ? $item->get_promise() : $item;
                yield $key => $promise instanceof Lazy_Promise ? $promise->build_promise() : $promise;
            }
        };
        (new Each_Promise($promise_generator(), ['fulfilled' => function ($result, $key) use (&$results): void {
            $results[$key] = $result;
        }, 'rejected' => function ($reason, $key) use (&$results): void {
            $results[$key] = $reason;
        }, 'concurrency' => $concurrency]))->promise()->wait();
        return $results;
    }
    /**
     * Send a pool of asynchronous requests concurrently, with callbacks for introspection.
     */
    public function batch(callable $callback): Batch
    {
        return tap(new Batch($this->factory), $callback);
    }
    /**
     * Send the request to the given URL.
     *
     * @return \Illuminate\Http\Client\Response|\Illuminate\Http\Client\Promises\LazyPromise
     *
     * @phpstan-return (TAsync is false ? \Illuminate\Http\Client\Response : \Illuminate\Http\Client\Promises\LazyPromise)
     *
     * @throws \Exception
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function send(string $method, string $url, array $options = [])
    {
        if (!Str::starts_with($url, ['http://', 'https://'])) {
            $url = ltrim(rtrim($this->base_url, '/') . '/' . ltrim($url, '/'), '/');
        }
        $url = $this->expand_url_parameters($url);
        $options = $this->parse_http_options($options);
        [$this->pending_body, $this->pending_files] = [null, []];
        if ($this->async) {
            return $this->promise = new Lazy_Promise(fn() => $this->make_promise($method, $url, $options));
        }
        $should_retry = null;
        return retry($this->tries ?? 1, function ($attempt) use ($method, $url, $options, &$should_retry) {
            try {
                return tap($this->new_response($this->send_request($method, $url, $options)), function (&$response) use ($attempt, &$should_retry): void {
                    $this->populate_response($response);
                    $this->dispatch_response_received_event($response);
                    $response = $this->run_after_response_callbacks($response);
                    if ($response->successful()) {
                        return;
                    }
                    try {
                        $should_retry = $this->retry_when_callback ? call_user_func($this->retry_when_callback, $response->to_exception(), $this, $this->request->to_psr_request()->get_method()) : true;
                    } catch (Exception $exception) {
                        $should_retry = false;
                        throw $exception;
                    }
                    if ($this->throw_callback && ($this->throw_if_callback === null || call_user_func($this->throw_if_callback, $response))) {
                        $response->throw($this->throw_callback);
                    }
                    $potential_tries = is_array($this->tries) ? count($this->tries) + 1 : $this->tries;
                    if ($attempt < $potential_tries && $should_retry) {
                        $response->throw();
                    }
                    if ($potential_tries > 1 && $this->retry_throw) {
                        $response->throw();
                    }
                });
            } catch (Transfer_Exception $e) {
                if ($e instanceof Connect_Exception) {
                    $this->marshal_connection_exception($e);
                }
                if ($e instanceof Request_Exception && !$e->has_response()) {
                    $this->marshal_request_exception_without_response($e);
                }
                if ($e instanceof Request_Exception && $e->has_response()) {
                    $this->marshal_request_exception_with_response($e);
                }
                throw $e;
            }
        }, $this->retry_delay ?? 100, function ($exception) use (&$should_retry) {
            $result = $should_retry ?? $this->retry_when_callback ? call_user_func($this->retry_when_callback, $exception, $this, $this->request?->to_psr_request()->get_method()) : true;
            $should_retry = null;
            return $result;
        });
    }
    /**
     * Substitute the URL parameters in the given URL.
     *
     * @return string
     */
    protected function expand_url_parameters(string $url)
    {
        return Uri_Template::expand($url, $this->url_parameters);
    }
    /**
     * Parse the given HTTP options and set the appropriate additional options.
     *
     * @return array
     */
    protected function parse_http_options(array $options)
    {
        if (isset($options[$this->body_format])) {
            if ($this->body_format === 'multipart') {
                $options[$this->body_format] = $this->parse_multipart_body_format($options[$this->body_format]);
            } elseif ($this->body_format === 'body') {
                $options[$this->body_format] = $this->pending_body;
            }
            if (is_array($options[$this->body_format])) {
                $options[$this->body_format] = array_merge($options[$this->body_format], $this->pending_files);
            }
        } else {
            $options[$this->body_format] = $this->pending_body;
        }
        return (new Collection($options))->map(function ($value, $key) {
            if ($key === 'json' && $value instanceof JsonSerializable) {
                return $value;
            }
            return $value instanceof Arrayable ? $value->to_array() : $value;
        })->all();
    }
    /**
     * Parse multi-part form data.
     *
     * @return array|array[]
     */
    protected function parse_multipart_body_format(array $data)
    {
        return (new Collection($data))->flat_map(function ($value, $key): array|\Illuminate\Support\Collection {
            if (is_array($value)) {
                // If the array has 'name' and 'contents' keys, it's already formatted for multipart...
                if (isset($value['name']) && isset($value['contents'])) {
                    return [$value];
                }
                // Otherwise, treat it as multiple values for the same field name...
                return (new Collection($value))->map(fn($item): array => ['name' => $key . '[]', 'contents' => $item]);
            }
            return [['name' => $key, 'contents' => $value]];
        })->values()->all();
    }
    /**
     * Send an asynchronous request to the given URL.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    protected function make_promise(string $method, string $url, array $options = [], int $attempt = 1)
    {
        return $this->promise = $this->send_request($method, $url, $options)->then(function (Message_Interface $message) {
            $response = $this->new_response($message);
            $this->populate_response($response);
            $this->dispatch_response_received_event($response);
            return $this->run_after_response_callbacks($response);
        })->otherwise(function (Throwable $e): \Illuminate\Http\Client\Connection_Exception|\Illuminate\Http\Client\Response|\Throwable {
            if ($e instanceof Stray_Request_Exception) {
                throw $e;
            }
            if ($e instanceof Connect_Exception || $e instanceof Request_Exception && !$e->has_response()) {
                $exception = new Connection_Exception($e->get_message(), 0, $e);
                $this->dispatch_connection_failed_event((new Request($e->get_request()))->set_request_attributes($this->attributes), $exception);
                return $exception;
            }
            return $e instanceof Request_Exception && $e->has_response() ? $this->populate_response($this->new_response($e->get_response())) : $e;
        })->then(fn(Response|Throwable $response): mixed => $this->handle_promise_response($response, $method, $url, $options, $attempt));
    }
    /**
     * Handle the response of an asynchronous request.
     *
     * @param  int  $attempt
     * @return mixed
     */
    protected function handle_promise_response(Response|Throwable $response, string $method, string $url, array $options, $attempt)
    {
        if ($response instanceof Response && $response->successful()) {
            return $response;
        }
        if ($response instanceof Request_Exception) {
            $response = $this->populate_response($this->new_response($response->get_response()));
        }
        try {
            $should_retry = $this->retry_when_callback ? call_user_func($this->retry_when_callback, $response instanceof Response ? $response->to_exception() : $response, $this) : true;
        } catch (Exception $exception) {
            return $exception;
        }
        if ($attempt < $this->tries && $should_retry) {
            $options['delay'] = value($this->retry_delay, $attempt, $response instanceof Response ? $response->to_exception() : $response);
            return $this->make_promise($method, $url, $options, $attempt + 1);
        }
        if ($response instanceof Response && $this->throw_callback && ($this->throw_if_callback === null || call_user_func($this->throw_if_callback, $response))) {
            try {
                $response->throw($this->throw_callback);
            } catch (Exception $exception) {
                return $exception;
            }
        }
        if ($this->tries > 1 && $this->retry_throw) {
            return $response instanceof Response ? $response->to_exception() : $response;
        }
        return $response;
    }
    /**
     * Send a request either synchronously or asynchronously.
     *
     * @return \Psr\Http\Message\MessageInterface|\GuzzleHttp\Promise\PromiseInterface
     *
     * @throws \Exception
     */
    protected function send_request(string $method, string $url, array $options = [])
    {
        $client_method = $this->async ? 'requestAsync' : 'request';
        $laravel_data = $this->parse_request_data($method, $url, $options);
        $on_stats = function ($transfer_stats): void {
            if (($callback = $this->options['on_stats'] ?? false) instanceof Closure) {
                $transfer_stats = $callback($transfer_stats) ?: $transfer_stats;
            }
            $this->transfer_stats = $transfer_stats;
        };
        $merged_options = $this->normalize_request_options($this->merge_options(['laravel_data' => $laravel_data, 'on_stats' => $on_stats], $options));
        $result = $this->build_client()->{$client_method}($method, $url, $merged_options);
        if ($result instanceof Promise_Interface && !$result instanceof Fluent_Promise) {
            return new Fluent_Promise($result);
        }
        return $result;
    }
    /**
     * Get the request data as an array so that we can attach it to the request for convenient assertions.
     *
     * @param  string  $method
     * @param  string  $url
     */
    protected function parse_request_data($method, $url, array $options): array
    {
        if ($this->body_format === 'body') {
            return [];
        }
        $laravel_data = $options[$this->body_format] ?? $options['query'] ?? [];
        $url_string = new Stringable($url);
        if (empty($laravel_data) && $method === 'GET' && $url_string->contains('?')) {
            $laravel_data = (string) $url_string->after('?');
        }
        if (is_string($laravel_data)) {
            parse_str($laravel_data, $parsed_data);
            $laravel_data = is_array($parsed_data) ? $parsed_data : [];
        }
        if ($laravel_data instanceof JsonSerializable) {
            $laravel_data = $laravel_data->jsonSerialize();
        }
        return is_array($laravel_data) ? $laravel_data : [];
    }
    /**
     * Normalize the given request options.
     */
    protected function normalize_request_options(array $options): array
    {
        foreach ($options as $key => $value) {
            $options[$key] = match (true) {
                is_array($value) => $this->normalize_request_options($value),
                $value instanceof Stringable => $value->to_string(),
                default => $value,
            };
        }
        return $options;
    }
    /**
     * Populate the given response with additional data.
     */
    protected function populate_response(Response $response): Response
    {
        $response->cookies = $this->cookies;
        $response->transfer_stats = $this->transfer_stats;
        return $response;
    }
    /**
     * Build the Guzzle client.
     *
     * @return \GuzzleHttp\Client
     */
    public function build_client()
    {
        return $this->client ?? $this->create_client($this->build_handler_stack());
    }
    /**
     * Determine if a reusable client is required.
     */
    protected function requests_reusable_client(): bool
    {
        return !is_null($this->client) || $this->async;
    }
    /**
     * Retrieve a reusable Guzzle client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function get_reusable_client()
    {
        return $this->client ??= $this->create_client($this->build_handler_stack());
    }
    /**
     * Create new Guzzle client.
     *
     * @param  \GuzzleHttp\HandlerStack  $handlerStack
     * @return \GuzzleHttp\Client
     */
    public function create_client($handler_stack)
    {
        return new Client(['handler' => $handler_stack, 'cookies' => true]);
    }
    /**
     * Build the Guzzle client handler stack.
     *
     * @return \GuzzleHttp\HandlerStack
     */
    public function build_handler_stack()
    {
        return $this->push_handlers(Handler_Stack::create($this->handler));
    }
    /**
     * Add the necessary handlers to the given handler stack.
     *
     * @param  \GuzzleHttp\HandlerStack  $handlerStack
     * @return \GuzzleHttp\HandlerStack
     */
    public function push_handlers($handler_stack)
    {
        return tap($handler_stack, function ($stack): void {
            $this->middleware->each(function ($middleware) use ($stack): void {
                $stack->push($middleware);
            });
            $stack->push($this->build_before_sending_handler());
            $stack->push($this->build_recorder_handler());
            $stack->push($this->build_stub_handler());
        });
    }
    /**
     * Build the before sending handler.
     *
     * @return \Closure
     */
    public function build_before_sending_handler()
    {
        return fn($handler): \Closure => fn($request, array $options) => $handler($this->run_before_sending_callbacks($request, $options), $options);
    }
    /**
     * Build the recorder handler.
     *
     * @return \Closure
     */
    public function build_recorder_handler()
    {
        return fn($handler): \Closure => function ($request, $options) use ($handler) {
            $promise = $handler($request, $options);
            return $promise->then(function ($response) use ($request, $options) {
                $this->factory?->record_request_response_pair((new Request($request))->with_data($options['laravel_data'])->set_request_attributes($this->attributes), $this->new_response($response));
                return $response;
            });
        };
    }
    /**
     * Build the stub handler.
     *
     * @return \Closure
     *
     * @throws \Illuminate\Http\Client\Exceptions\StrayRequestException
     */
    public function build_stub_handler()
    {
        return fn($handler): \Closure => function ($request, array $options) use ($handler) {
            $response = ($this->stub_callbacks ?? new Collection())->map->__invoke((new Request($request))->with_data($options['laravel_data'])->set_request_attributes($this->attributes), $options)->filter()->first();
            if (is_null($response)) {
                if (!$this->is_allowed_request_url((string) $request->get_uri())) {
                    throw new Stray_Request_Exception((string) $request->get_uri());
                }
                return $handler($request, $options);
            }
            $response = is_array($response) ? Factory::response($response) : $response;
            $sink = $options['sink'] ?? null;
            if ($sink) {
                $response->then($this->sink_stub_handler($sink));
            }
            return $response;
        };
    }
    /**
     * Get the sink stub handler callback.
     *
     * @param  string  $sink
     * @return \Closure
     */
    protected function sink_stub_handler($sink)
    {
        return function ($response) use ($sink): void {
            $body = $response->get_body()->get_contents();
            if (is_string($sink)) {
                file_put_contents($sink, $body);
                return;
            }
            fwrite($sink, $body);
            rewind($sink);
        };
    }
    /**
     * Execute the "before sending" callbacks.
     *
     * @param  \Psr\Http\Message\RequestInterface  $request
     * @return \Psr\Http\Message\RequestInterface
     */
    public function run_before_sending_callbacks($request, array $options)
    {
        return tap($request, function (&$request) use ($options): void {
            $this->before_sending_callbacks->each(function ($callback) use (&$request, $options): void {
                $callback_result = call_user_func($callback, (new Request($request))->with_data($options['laravel_data'])->set_request_attributes($this->attributes), $options, $this);
                if ($callback_result instanceof Request_Interface) {
                    $request = $callback_result;
                } elseif ($callback_result instanceof Request) {
                    $request = $callback_result->to_psr_request();
                }
            });
        });
    }
    /**
     * Replace the given options with the current request options.
     *
     * @param  array  ...$options
     */
    public function merge_options(...$options): array
    {
        return array_replace_recursive(array_merge_recursive($this->options, Arr::only($options, $this->mergeable_options)), ...$options);
    }
    /**
     * Create a new response instance using the given PSR response.
     *
     * @param  \Psr\Http\Message\MessageInterface  $response
     * @return Response
     */
    protected function new_response($response)
    {
        return tap(new Response($response), function (Response $laravel_response): void {
            if ($this->truncate_exceptions_at === null) {
                return;
            }
            $this->truncate_exceptions_at === false ? $laravel_response->dont_truncate_exceptions() : $laravel_response->truncate_exceptions_at($this->truncate_exceptions_at);
        });
    }
    /**
     * Execute the "after response" callbacks.
     *
     * @return \Illuminate\Http\Client\Response
     */
    protected function run_after_response_callbacks(Response $response)
    {
        foreach ($this->after_response_callbacks as $callback) {
            $returned_response = $callback($response);
            if ($returned_response instanceof Response) {
                $response = $returned_response;
            }
        }
        return $response;
    }
    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function stub($callback): static
    {
        $this->stub_callbacks = new Collection($callback);
        return $this;
    }
    /**
     * Indicate that an exception should be thrown if any request is not faked.
     *
     * @param  bool  $prevent
     * @return $this
     */
    public function prevent_stray_requests($prevent = true): static
    {
        $this->prevent_stray_requests = $prevent;
        return $this;
    }
    /**
     * Allow stray, unfaked requests entirely, or optionally allow only specific URLs.
     *
     * @param  array<int, string>  $only
     * @return $this
     */
    public function allow_stray_requests(array $only): static
    {
        $this->allowed_stray_request_urls = array_values($only);
        return $this;
    }
    /**
     * Determine if the given URL is allowed as a stray request.
     *
     * @param  string  $url
     */
    public function is_allowed_request_url($url): bool
    {
        if (!$this->prevent_stray_requests) {
            return true;
        }
        foreach ($this->allowed_stray_request_urls as $pattern) {
            if (Str::is($pattern, $url)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Toggle asynchronicity in requests.
     *
     * @template T of bool = true
     *
     * @param  T  $async
     * @return self<T>
     *
     * @phpstan-self-out self<T>
     */
    public function async(bool $async = true): static
    {
        $this->async = $async;
        return $this;
    }
    /**
     * Retrieve the pending request promise.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|null
     */
    public function get_promise()
    {
        return $this->promise;
    }
    /**
     * Dispatch the RequestSending event if a dispatcher is available.
     *
     * @return void
     */
    protected function dispatch_request_sending_event()
    {
        if ($dispatcher = $this->factory?->get_dispatcher()) {
            $dispatcher->dispatch(new Request_Sending($this->request));
        }
    }
    /**
     * Dispatch the ResponseReceived event if a dispatcher is available.
     *
     * @return void
     */
    protected function dispatch_response_received_event(Response $response)
    {
        if (!($dispatcher = $this->factory?->get_dispatcher()) || !$this->request) {
            return;
        }
        $dispatcher->dispatch(new Response_Received($this->request, $response));
    }
    /**
     * Dispatch the ConnectionFailed event if a dispatcher is available.
     *
     * @return void
     */
    protected function dispatch_connection_failed_event(Request $request, Connection_Exception $exception)
    {
        if ($dispatcher = $this->factory?->get_dispatcher()) {
            $dispatcher->dispatch(new Connection_Failed($request, $exception));
        }
    }
    /**
     * Indicate that request exceptions should be truncated to the given length.
     *
     * @param  int<1, max>  $length
     * @return $this
     */
    public function truncate_exceptions_at(int $length): static
    {
        $this->truncate_exceptions_at = $length;
        return $this;
    }
    /**
     * Indicate that request exceptions should not be truncated.
     *
     * @return $this
     */
    public function dont_truncate_exceptions(): static
    {
        $this->truncate_exceptions_at = false;
        return $this;
    }
    /**
     * Handle the given connection exception.
     *
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function marshal_connection_exception(Connect_Exception $e): never
    {
        $exception = new Connection_Exception($e->get_message(), 0, $e);
        $request = (new Request($e->get_request()))->set_request_attributes($this->attributes);
        $this->factory?->record_request_response_pair($request, null);
        $this->dispatch_connection_failed_event($request, $exception);
        throw $exception;
    }
    /**
     * Handle the given request exception.
     *
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function marshal_request_exception_without_response(Request_Exception $e): never
    {
        $exception = new Connection_Exception($e->get_message(), 0, $e);
        $request = (new Request($e->get_request()))->set_request_attributes($this->attributes);
        $this->factory?->record_request_response_pair($request, null);
        $this->dispatch_connection_failed_event($request, $exception);
        throw $exception;
    }
    /**
     * Handle the given request exception.
     *
     *
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function marshal_request_exception_with_response(Request_Exception $e): never
    {
        $response = $this->populate_response($this->new_response($e->get_response()));
        $this->factory?->record_request_response_pair((new Request($e->get_request()))->set_request_attributes($this->attributes), $response);
        throw $response->to_exception() ?? new Connection_Exception($e->get_message(), 0, $e);
    }
    /**
     * Set the client instance.
     *
     * @return $this
     */
    public function set_client(Client $client): static
    {
        $this->client = $client;
        return $this;
    }
    /**
     * Create a new client instance using the given handler.
     *
     * @param  callable  $handler
     * @return $this
     */
    public function set_handler($handler): static
    {
        $this->handler = $handler;
        return $this;
    }
    /**
     * Get the pending request options.
     */
    public function get_options(): array
    {
        return $this->options;
    }
}