<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

use Guzzle_Http\Utils;
/**
 * @mixin \Illuminate\Http\Client\Factory
 */
class Pool
{
    /**
     * The factory instance.
     */
    protected \Illuminate\Http\Client\Factory $factory;
    /**
     * The handler function for the Guzzle client.
     *
     * @var callable
     */
    protected $handler;
    /**
     * The pool of requests.
     *
     * @var array<array-key, \Illuminate\Http\Client\PendingRequest>
     */
    protected $pool = [];
    /**
     * Create a new requests pool.
     */
    public function __construct(?Factory $factory = null)
    {
        $this->factory = $factory ?: new Factory();
        $this->handler = Utils::choose_handler();
    }
    /**
     * Add a request to the pool with a numeric index.
     *
     * @return \Illuminate\Http\Client\PendingRequest|\GuzzleHttp\Promise\Promise
     */
    public function new_request()
    {
        return $this->pool[] = $this->async_request();
    }
    /**
     * Add a request to the pool with a key.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function as(string $key)
    {
        return $this->pool[$key] = $this->async_request();
    }
    /**
     * Retrieve a new async pending request.
     */
    protected function async_request(): \Illuminate\Http\Client\Pending_Request
    {
        return $this->factory->set_handler($this->handler)->async();
    }
    /**
     * Retrieve the requests in the pool.
     *
     * @return array<array-key, \Illuminate\Http\Client\PendingRequest>
     */
    public function get_requests()
    {
        return $this->pool;
    }
    /**
     * Add a request to the pool with a numeric index and forward the method call to the request.
     *
     * @return \Illuminate\Http\Client\PendingRequest|\GuzzleHttp\Promise\Promise
     */
    public function __call(string $method, array $parameters)
    {
        return $this->new_request()->{$method}(...$parameters);
    }
}