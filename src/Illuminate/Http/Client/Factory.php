<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

use Closure;
use Guzzle_Http\Exception\Connect_Exception;
use Guzzle_Http\Middleware;
use Guzzle_Http\Promise\Create;
use Guzzle_Http\Promise\Promise_Interface;
use Guzzle_Http\Psr7\Response as Psr7Response;
use Guzzle_Http\Transfer_Stats;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Php_Unit\Framework\Assert as PHPUnit;
/**
 * @mixin \Illuminate\Http\Client\PendingRequest
 */
class Factory
{
    use Macroable {
        __call as macroCall;
    }
    /**
     * The middleware to apply to every request.
     *
     * @var array
     */
    protected $global_middleware = [];
    /**
     * The options to apply to every request.
     *
     * @var \Closure|array
     */
    protected $global_options = [];
    /**
     * The stub callables that will handle requests.
     */
    protected \Illuminate\Support\Collection $stub_callbacks;
    /**
     * Indicates if the factory is recording requests and responses.
     *
     * @var bool
     */
    protected $recording = false;
    /**
     * The recorded response array.
     *
     * @var list<array{0: \Illuminate\Http\Client\Request, 1: \Illuminate\Http\Client\Response|null}>
     */
    protected $recorded = [];
    /**
     * All created response sequences.
     *
     * @var list<\Illuminate\Http\Client\ResponseSequence>
     */
    protected $response_sequences = [];
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
     * Create a new factory instance.
     */
    public function __construct(
        /**
         * The event dispatcher implementation.
         */
        protected ?\Illuminate\Contracts\Events\Dispatcher $dispatcher = null
    )
    {
        $this->stub_callbacks = new Collection();
    }
    /**
     * Add middleware to apply to every request.
     *
     * @param  callable  $middleware
     * @return $this
     */
    public function global_middleware($middleware): static
    {
        $this->global_middleware[] = $middleware;
        return $this;
    }
    /**
     * Add request middleware to apply to every request.
     *
     * @param  callable  $middleware
     * @return $this
     */
    public function global_request_middleware($middleware): static
    {
        $this->global_middleware[] = Middleware::map_request($middleware);
        return $this;
    }
    /**
     * Add response middleware to apply to every request.
     *
     * @param  callable  $middleware
     * @return $this
     */
    public function global_response_middleware($middleware): static
    {
        $this->global_middleware[] = Middleware::map_response($middleware);
        return $this;
    }
    /**
     * Set the options to apply to every request.
     *
     * @param  \Closure|array  $options
     * @return $this
     */
    public function global_options($options): static
    {
        $this->global_options = $options;
        return $this;
    }
    /**
     * Create a new response instance for use during stubbing.
     *
     * @param  array|string|null  $body
     * @param  int  $status
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function response($body = null, $status = 200, array $headers = [])
    {
        return Create::promise_for(static::psr7Response($body, $status, $headers));
    }
    /**
     * Create a new PSR-7 response instance for use during stubbing.
     *
     * @param  array|string|null  $body
     * @param  int  $status
     * @param  array<string, mixed>  $headers
     * @return \GuzzleHttp\Psr7\Response
     */
    public static function psr7Response($body = null, $status = 200, array $headers = [])
    {
        if (is_array($body)) {
            $body = json_encode($body);
            $headers['Content-Type'] = 'application/json';
        }
        return new Psr7Response($status, $headers, $body);
    }
    /**
     * Create a new RequestException instance for use during stubbing.
     *
     * @param  array|string|null  $body
     * @param  int  $status
     * @param  array<string, mixed>  $headers
     */
    public static function failed_request($body = null, $status = 200, array $headers = []): \Illuminate\Http\Client\Request_Exception
    {
        return new Request_Exception(new Response(static::psr7Response($body, $status, $headers)));
    }
    /**
     * Create a new connection exception for use during stubbing.
     *
     * @param  string|null  $message
     * @return \Closure(\Illuminate\Http\Client\Request): \GuzzleHttp\Promise\PromiseInterface
     */
    public static function failed_connection($message = null)
    {
        return fn($request) => Create::rejection_for(new Connect_Exception($message ?? "cURL error 6: Could not resolve host: {$request->to_psr_request()->get_uri()->get_host()} (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for {$request->to_psr_request()->get_uri()}.", $request->to_psr_request()));
    }
    /**
     * Get an invokable object that returns a sequence of responses in order for use during stubbing.
     *
     * @return \Illuminate\Http\Client\ResponseSequence
     */
    public function sequence(array $responses = [])
    {
        return $this->response_sequences[] = new Response_Sequence($responses);
    }
    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param  callable|array<string, mixed>|null  $callback
     * @return $this
     */
    public function fake($callback = null): static
    {
        $this->record();
        $this->recorded = [];
        if (is_null($callback)) {
            $callback = fn() => static::response();
        }
        if (is_array($callback)) {
            foreach ($callback as $url => $callable) {
                $this->stub_url($url, $callable);
            }
            return $this;
        }
        $this->stub_callbacks = $this->stub_callbacks->merge(new Collection([function ($request, array $options) use ($callback) {
            $response = $callback;
            while ($response instanceof Closure) {
                $response = $response($request, $options);
            }
            if ($response instanceof Promise_Interface) {
                $options['on_stats'](new Transfer_Stats($request->to_psr_request(), $response->wait()));
            }
            return $response;
        }]));
        return $this;
    }
    /**
     * Register a response sequence for the given URL pattern.
     *
     * @param  string  $url
     * @return \Illuminate\Http\Client\ResponseSequence
     */
    public function fake_sequence($url = '*')
    {
        return tap($this->sequence(), function ($sequence) use ($url): void {
            $this->fake([$url => $sequence]);
        });
    }
    /**
     * Stub the given URL using the given callback.
     *
     * @param  string  $url
     * @param  \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface|callable|int|string|array|\Illuminate\Http\Client\ResponseSequence  $callback
     * @return $this
     */
    public function stub_url($url, $callback): static
    {
        return $this->fake(function ($request, $options) use ($url, $callback) {
            if (!Str::is(Str::start($url, '*'), $request->url())) {
                return;
            }
            if (is_int($callback) && $callback >= 100 && $callback < 600) {
                return static::response(status: $callback);
            }
            if (is_int($callback) || is_string($callback)) {
                return static::response($callback);
            }
            if ($callback instanceof Closure || $callback instanceof Response_Sequence) {
                return $callback($request, $options);
            }
            return $callback;
        });
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
     * Determine if stray requests are being prevented.
     *
     * @return bool
     */
    public function preventing_stray_requests()
    {
        return $this->prevent_stray_requests;
    }
    /**
     * Allow stray, unfaked requests entirely, or optionally allow only specific URLs.
     *
     * @param  array<int, string>|null  $only
     * @return $this
     */
    public function allow_stray_requests(?array $only = null): static
    {
        if (is_null($only)) {
            $this->prevent_stray_requests(false);
            $this->allowed_stray_request_urls = [];
        } else {
            $this->allowed_stray_request_urls = array_values($only);
        }
        return $this;
    }
    /**
     * Begin recording request / response pairs.
     *
     * @return $this
     */
    public function record(): static
    {
        $this->recording = true;
        return $this;
    }
    /**
     * Record a request response pair.
     *
     * @param  \Illuminate\Http\Client\Request  $request
     * @param  \Illuminate\Http\Client\Response|null  $response
     */
    public function record_request_response_pair($request, $response): void
    {
        if ($this->recording) {
            $this->recorded[] = [$request, $response];
        }
    }
    /**
     * Assert that a request / response pair was recorded matching a given truth test.
     *
     * @param  callable|(\Closure(\Illuminate\Http\Client\Request, \Illuminate\Http\Client\Response|null): bool)  $callback
     */
    public function assert_sent($callback): void
    {
        Php_Unit::assert_true($this->recorded($callback)->count() > 0, 'An expected request was not recorded.');
    }
    /**
     * Assert that the given request was sent in the given order.
     *
     * @param  list<string|(\Closure(\Illuminate\Http\Client\Request, \Illuminate\Http\Client\Response|null): bool)|callable>  $callbacks
     */
    public function assert_sent_in_order($callbacks): void
    {
        $this->assert_sent_count(count($callbacks));
        foreach ($callbacks as $index => $url) {
            $callback = is_callable($url) ? $url : fn($request): bool => $request->url() == $url;
            Php_Unit::assert_true($callback($this->recorded[$index][0], $this->recorded[$index][1]), 'An expected request (#' . ($index + 1) . ') was not recorded.');
        }
    }
    /**
     * Assert that a request / response pair was not recorded matching a given truth test.
     *
     * @param  callable|(\Closure(\Illuminate\Http\Client\Request, \Illuminate\Http\Client\Response|null): bool)  $callback
     */
    public function assert_not_sent($callback): void
    {
        Php_Unit::assert_false($this->recorded($callback)->count() > 0, 'Unexpected request was recorded.');
    }
    /**
     * Assert that no request / response pair was recorded.
     */
    public function assert_nothing_sent(): void
    {
        Php_Unit::assert_empty($this->recorded, 'Requests were recorded.');
    }
    /**
     * Assert how many requests have been recorded.
     *
     * @param  int  $count
     */
    public function assert_sent_count($count): void
    {
        Php_Unit::assert_count($count, $this->recorded);
    }
    /**
     * Assert that every created response sequence is empty.
     */
    public function assert_sequences_are_empty(): void
    {
        foreach ($this->response_sequences as $response_sequence) {
            Php_Unit::assert_true($response_sequence->is_empty(), 'Not all response sequences are empty.');
        }
    }
    /**
     * Get a collection of the request / response pairs matching the given truth test.
     *
     * @param  (\Closure(\Illuminate\Http\Client\Request, \Illuminate\Http\Client\Response|null): bool)|callable  $callback
     * @return \Illuminate\Support\Collection<int, array{0: \Illuminate\Http\Client\Request, 1: \Illuminate\Http\Client\Response|null}>
     */
    public function recorded($callback = null): \Illuminate\Support\Collection
    {
        if (empty($this->recorded)) {
            return new Collection();
        }
        $collect = new Collection($this->recorded);
        if ($callback) {
            return $collect->filter(fn($pair) => $callback($pair[0], $pair[1]));
        }
        return $collect;
    }
    /**
     * Create a new pending request instance for this factory.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function create_pending_request()
    {
        return tap($this->new_pending_request(), function ($request): void {
            $request->stub($this->stub_callbacks)->prevent_stray_requests($this->prevent_stray_requests)->allow_stray_requests($this->allowed_stray_request_urls);
        });
    }
    /**
     * Instantiate a new pending request instance for this factory.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function new_pending_request()
    {
        return (new Pending_Request($this, $this->global_middleware))->with_options(value($this->global_options));
    }
    /**
     * Get the current event dispatcher implementation.
     */
    public function get_dispatcher(): ?\Illuminate\Contracts\Events\Dispatcher
    {
        return $this->dispatcher;
    }
    /**
     * Get the array of global middleware.
     *
     * @return array
     */
    public function get_global_middleware()
    {
        return $this->global_middleware;
    }
    /**
     * Execute a method against a new pending request instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        return $this->create_pending_request()->{$method}(...$parameters);
    }
}