<?php

declare (strict_types=1);
namespace Illuminate\Http\Client\Promises;

use Closure;
use Guzzle_Http\Promise\Promise_Interface;
use RuntimeException;
class Lazy_Promise implements Promise_Interface
{
    /**
     * The callbacks to execute after the Guzzle Promise has been built.
     *
     * @var list<callable>
     */
    protected array $pending = [];
    /**
     * The promise built by the creator.
     */
    protected Promise_Interface $guzzle_promise;
    /**
     * Create a new lazy promise instance.
     *
     * @param  (\Closure(): \GuzzleHttp\Promise\PromiseInterface)  $promiseBuilder  The callback to build a new PromiseInterface.
     */
    public function __construct(protected Closure $promise_builder)
    {
    }
    /**
     * Build the promise from the promise builder.
     *
     *
     * @throws \RuntimeException If the promise has already been built
     */
    public function build_promise(): Promise_Interface
    {
        if (!$this->promise_needs_built()) {
            throw new RuntimeException('Promise already built');
        }
        $this->guzzle_promise = call_user_func($this->promise_builder);
        foreach ($this->pending as $pending_callback) {
            $pending_callback($this->guzzle_promise);
        }
        $this->pending = [];
        return $this->guzzle_promise;
    }
    #[\Override]
    public function then(?callable $on_fulfilled = null, ?callable $on_rejected = null): Promise_Interface
    {
        if ($this->promise_needs_built()) {
            $this->pending[] = static fn(Promise_Interface $promise) => $promise->then($on_fulfilled, $on_rejected);
            return $this;
        }
        return $this->guzzle_promise->then($on_fulfilled, $on_rejected);
    }
    #[\Override]
    public function otherwise(callable $on_rejected): Promise_Interface
    {
        if ($this->promise_needs_built()) {
            $this->pending[] = static fn(Promise_Interface $promise) => $promise->otherwise($on_rejected);
            return $this;
        }
        return $this->guzzle_promise->otherwise($on_rejected);
    }
    #[\Override]
    public function get_state(): string
    {
        if ($this->promise_needs_built()) {
            return Promise_Interface::PENDING;
        }
        return $this->guzzle_promise->get_state();
    }
    #[\Override]
    public function resolve($value): void
    {
        throw new \LogicException('Cannot resolve a lazy promise.');
    }
    #[\Override]
    public function reject($reason): void
    {
        throw new \LogicException('Cannot reject a lazy promise.');
    }
    #[\Override]
    public function cancel(): void
    {
        throw new \LogicException('Cannot cancel a lazy promise.');
    }
    #[\Override]
    public function wait(bool $unwrap = true)
    {
        if ($this->promise_needs_built()) {
            $this->build_promise();
        }
        return $this->guzzle_promise->wait($unwrap);
    }
    /**
     * Determine if the promise has been created from the promise builder.
     */
    public function promise_needs_built(): bool
    {
        return !isset($this->guzzle_promise);
    }
}