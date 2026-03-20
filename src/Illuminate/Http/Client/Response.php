<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

use ArrayAccess;
use Guzzle_Http\Psr7\Stream_Wrapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use LogicException;
use Stringable;
/**
 * @mixin \Psr\Http\Message\ResponseInterface
 */
class Response implements ArrayAccess, Stringable
{
    use Concerns\Determines_Status_Code, Tappable, Macroable {
        __call as macroCall;
    }
    /**
     * The underlying PSR response.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;
    /**
     * The decoded JSON response.
     *
     * @var array
     */
    protected $decoded;
    /**
     * The flags that were used when decoding the JSON response.
     *
     * @var int-mask<JSON_BIGINT_AS_STRING, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE, JSON_OBJECT_AS_ARRAY, JSON_THROW_ON_ERROR>
     */
    protected int $decoding_flags;
    /**
     * The request cookies.
     *
     * @var \GuzzleHttp\Cookie\CookieJar
     */
    public $cookies;
    /**
     * The transfer stats for the request.
     *
     * @var \GuzzleHttp\TransferStats|null
     */
    public $transfer_stats;
    /**
     * The length at which request exceptions will be truncated.
     *
     * @var int<1, max>|false|null
     */
    protected $truncate_exceptions_at;
    /**
     * The flags passed to `json_decode` by default.
     *
     * @var int-mask<JSON_BIGINT_AS_STRING, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE, JSON_OBJECT_AS_ARRAY, JSON_THROW_ON_ERROR>
     */
    public static int $default_json_decoding_flags = 0;
    /**
     * Create a new response instance.
     *
     * @param  \Psr\Http\Message\MessageInterface  $response
     */
    public function __construct($response)
    {
        $this->response = $response;
    }
    /**
     * Get the body of the response.
     */
    public function body(): string
    {
        return (string) $this->response->get_body();
    }
    /**
     * Get the decoded JSON body of the response as an array or scalar value.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @param  int-mask<JSON_BIGINT_AS_STRING, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE, JSON_OBJECT_AS_ARRAY, JSON_THROW_ON_ERROR>|null  $flags
     * @return mixed
     */
    public function json($key = null, $default = null, $flags = null)
    {
        $flags ??= self::$default_json_decoding_flags;
        if (!$this->decoded || isset($this->decoding_flags) && $this->decoding_flags !== $flags) {
            $this->decoded = json_decode($this->body(), true, flags: $flags);
            $this->decoding_flags = $flags;
        }
        if (is_null($key)) {
            return $this->decoded;
        }
        return data_get($this->decoded, $key, $default);
    }
    /**
     * Get the decoded JSON body of the response as an object.
     *
     * @param  int-mask<JSON_BIGINT_AS_STRING, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE, JSON_OBJECT_AS_ARRAY, JSON_THROW_ON_ERROR>|null  $flags
     * @return object|null
     */
    public function object($flags = null): mixed
    {
        return json_decode($this->body(), false, flags: $flags ?? self::$default_json_decoding_flags);
    }
    /**
     * Get the decoded JSON body of the response as a collection.
     *
     * @param  string|null  $key
     * @param  int-mask<JSON_BIGINT_AS_STRING, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE, JSON_OBJECT_AS_ARRAY, JSON_THROW_ON_ERROR>|null  $flags
     */
    public function collect($key = null, $flags = null): \Illuminate\Support\Collection
    {
        return new Collection($this->json($key, flags: $flags));
    }
    /**
     * Get the decoded JSON body of the response as a fluent object.
     *
     * @param  string|null  $key
     * @param  int-mask<JSON_BIGINT_AS_STRING, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE, JSON_OBJECT_AS_ARRAY, JSON_THROW_ON_ERROR>|null  $flags
     */
    public function fluent($key = null, $flags = null): \Illuminate\Support\Fluent
    {
        return new Fluent((array) $this->json($key, flags: $flags));
    }
    /**
     * Get the body of the response as a PHP resource.
     *
     * @return resource
     *
     * @throws \InvalidArgumentException
     */
    public function resource()
    {
        return Stream_Wrapper::get_resource($this->response->get_body());
    }
    /**
     * Get a header from the response.
     *
     * @return string
     */
    public function header(string $header)
    {
        return $this->response->get_header_line($header);
    }
    /**
     * Get the headers from the response.
     *
     * @return array
     */
    public function headers()
    {
        return $this->response->get_headers();
    }
    /**
     * Get the status code of the response.
     */
    public function status(): int
    {
        return (int) $this->response->get_status_code();
    }
    /**
     * Get the reason phrase of the response.
     *
     * @return string
     */
    public function reason()
    {
        return $this->response->get_reason_phrase();
    }
    /**
     * Get the effective URI of the response.
     *
     * @return \Psr\Http\Message\UriInterface|null
     */
    public function effective_uri()
    {
        return $this->transfer_stats?->get_effective_uri();
    }
    /**
     * Determine if the request was successful.
     */
    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }
    /**
     * Determine if the response was a redirect.
     */
    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }
    /**
     * Determine if the response indicates a client or server error occurred.
     */
    public function failed(): bool
    {
        if ($this->server_error()) {
            return true;
        }
        return $this->client_error();
    }
    /**
     * Determine if the response indicates a client error occurred.
     */
    public function client_error(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }
    /**
     * Determine if the response indicates a server error occurred.
     */
    public function server_error(): bool
    {
        return $this->status() >= 500;
    }
    /**
     * Execute the given callback if there was a server or client error.
     *
     * @param  callable|(\Closure(\Illuminate\Http\Client\Response): mixed)  $callback
     * @return $this
     */
    public function on_error(callable $callback): static
    {
        if ($this->failed()) {
            $callback($this);
        }
        return $this;
    }
    /**
     * Get the response cookies.
     *
     * @return \GuzzleHttp\Cookie\CookieJar
     */
    public function cookies()
    {
        return $this->cookies;
    }
    /**
     * Get the handler stats of the response.
     *
     * @return array
     */
    public function handler_stats()
    {
        return $this->transfer_stats?->get_handler_stats() ?? [];
    }
    /**
     * Close the stream and any underlying resources.
     *
     * @return $this
     */
    public function close(): static
    {
        $this->response->get_body()->close();
        return $this;
    }
    /**
     * Get the underlying PSR response for the response.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function to_psr_response()
    {
        return $this->response;
    }
    /**
     * Create an exception if a server or client error occurred.
     *
     * @return \Illuminate\Http\Client\RequestException|null
     */
    public function to_exception()
    {
        if ($this->failed()) {
            return new Request_Exception($this, $this->truncate_exceptions_at);
        }
    }
    /**
     * Throw an exception if a server or client error occurred.
     *
     * @return $this
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function throw(): static
    {
        $callback = func_get_args()[0] ?? null;
        if ($this->failed()) {
            throw tap($this->to_exception(), function ($exception) use ($callback): void {
                if ($callback && is_callable($callback)) {
                    $callback($this, $exception);
                }
            });
        }
        return $this;
    }
    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to true.
     *
     * @param  \Closure|bool  $condition
     * @return $this
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function throw_if($condition): static
    {
        return value($condition, $this) ? $this->throw(func_get_args()[1] ?? null) : $this;
    }
    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to false.
     *
     * @param  \Closure|bool  $condition
     * @return $this
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function throw_unless($condition)
    {
        return $this->throw_if(!$condition);
    }
    /**
     * Throw an exception if the response status code matches the given code.
     *
     * @param  int|(\Closure(int, \Illuminate\Http\Client\Response): bool)|callable  $statusCode
     * @return $this
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function throw_if_status($status_code): static
    {
        if (is_callable($status_code) && $status_code($this->status(), $this)) {
            throw new Request_Exception($this, $this->truncate_exceptions_at);
        }
        return $this->status() === $status_code ? throw new Request_Exception($this, $this->truncate_exceptions_at) : $this;
    }
    /**
     * Throw an exception unless the response status code matches the given code.
     *
     * @param  int|(\Closure(int, \Illuminate\Http\Client\Response): bool)|callable  $statusCode
     * @return $this
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function throw_unless_status($status_code): static
    {
        if (is_callable($status_code)) {
            return $status_code($this->status(), $this) ? $this : throw new Request_Exception($this, $this->truncate_exceptions_at);
        }
        return $this->status() === $status_code ? $this : throw new Request_Exception($this, $this->truncate_exceptions_at);
    }
    /**
     * Throw an exception if the response status code is a 4xx level code.
     *
     * @return $this
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function throw_if_client_error(): static
    {
        return $this->client_error() ? $this->throw() : $this;
    }
    /**
     * Throw an exception if the response status code is a 5xx level code.
     *
     * @return $this
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function throw_if_server_error(): static
    {
        return $this->server_error() ? $this->throw() : $this;
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
     * Dump the content from the response.
     *
     * @param  string|null  $key
     * @return $this
     */
    public function dump($key = null): static
    {
        $content = $this->body();
        $json = json_decode($content);
        if (json_last_error() === JSON_ERROR_NONE) {
            $content = $json;
        }
        if (!is_null($key)) {
            dump(data_get($content, $key));
        } else {
            dump($content);
        }
        return $this;
    }
    /**
     * Dump the content from the response and end the script.
     *
     * @param  string|null  $key
     */
    public function dd($key = null): never
    {
        $this->dump($key);
        exit(1);
    }
    /**
     * Dump the headers from the response.
     *
     * @return $this
     */
    public function dump_headers(): static
    {
        dump($this->headers());
        return $this;
    }
    /**
     * Dump the headers from the response and end the script.
     */
    public function dd_headers(): never
    {
        $this->dump_headers();
        exit(1);
    }
    /**
     * Determine if the given offset exists.
     *
     * @param  string  $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->json()[$offset]);
    }
    /**
     * Get the value for a given offset.
     *
     * @param  string  $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->json()[$offset];
    }
    /**
     * Set the value at the given offset.
     *
     * @param  string  $offset
     * @param  mixed  $value
     *
     * @throws \LogicException
     */
    public function offsetSet($offset, $value): void
    {
        throw new LogicException('Response data may not be mutated using array access.');
    }
    /**
     * Unset the value at the given offset.
     *
     * @param  string  $offset
     *
     * @throws \LogicException
     */
    public function offsetUnset($offset): void
    {
        throw new LogicException('Response data may not be mutated using array access.');
    }
    /**
     * Get the body of the response.
     */
    public function __toString(): string
    {
        return $this->body();
    }
    /**
     * Dynamically proxy other methods to the underlying response.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return static::has_macro($method) ? $this->macro_call($method, $parameters) : $this->response->{$method}(...$parameters);
    }
    /**
     * Flush the global state of the Response.
     */
    public static function flush_state(): void
    {
        self::$default_json_decoding_flags = 0;
    }
}