<?php

declare (strict_types=1);
namespace Illuminate\Http\Client\Promises;

use Guzzle_Http\Promise\Promise_Interface;
use Illuminate\Support\Traits\Forwards_Calls;
/**
 * A decorated Promise which allows for chaining callbacks.
 */
class Fluent_Promise implements Promise_Interface
{
    use Forwards_Calls;
    /**
     * Create a new fluent promise instance.
     */
    public function __construct(protected Promise_Interface $guzzle_promise)
    {
    }
    #[\Override]
    public function then(?callable $on_fulfilled = null, ?callable $on_rejected = null): Promise_Interface
    {
        return $this->__call('then', [$on_fulfilled, $on_rejected]);
    }
    #[\Override]
    public function otherwise(callable $on_rejected): Promise_Interface
    {
        return $this->__call('otherwise', [$on_rejected]);
    }
    #[\Override]
    public function resolve($value): void
    {
        $this->guzzle_promise->resolve($value);
    }
    #[\Override]
    public function reject($reason): void
    {
        $this->guzzle_promise->reject($reason);
    }
    #[\Override]
    public function cancel(): void
    {
        $this->guzzle_promise->cancel();
    }
    #[\Override]
    public function wait(bool $unwrap = true)
    {
        return $this->__call('wait', [$unwrap]);
    }
    #[\Override]
    public function get_state(): string
    {
        return $this->guzzle_promise->get_state();
    }
    /**
     * Get the underlying Guzzle promise.
     */
    public function get_guzzle_promise(): Promise_Interface
    {
        return $this->guzzle_promise;
    }
    /**
     * Proxy requests to the underlying promise interface and update the local promise.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $result = $this->forward_call_to($this->guzzle_promise, $method, $parameters);
        if (!$result instanceof Promise_Interface) {
            return $result;
        }
        $this->guzzle_promise = $result;
        return $this;
    }
}