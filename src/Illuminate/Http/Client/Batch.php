<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

use Carbon\Carbon_Immutable;
use Closure;
use Guzzle_Http\Exception\Request_Exception;
use Guzzle_Http\Promise\Each_Promise;
use Guzzle_Http\Utils;
use Illuminate\Http\Client\Promises\Lazy_Promise;
use function Illuminate\Support\defer;
use Illuminate\Support\Defer\Deferred_Callback;
/**
 * @mixin \Illuminate\Http\Client\Factory
 */
class Batch
{
    /**
     * The factory instance.
     */
    protected \Illuminate\Http\Client\Factory $factory;
    /**
     * The array of requests.
     *
     * @var array<array-key, \Illuminate\Http\Client\PendingRequest>
     */
    protected $requests = [];
    /**
     * The total number of requests that belong to the batch.
     *
     * @var non-negative-int
     */
    public $total_requests = 0;
    /**
     * The total number of requests that are still pending.
     *
     * @var non-negative-int
     */
    public $pending_requests = 0;
    /**
     * The total number of requests that have failed.
     *
     * @var non-negative-int
     */
    public $failed_requests = 0;
    /**
     * The handler function for the Guzzle client.
     *
     * @var callable
     */
    protected $handler;
    /**
     * The callback to run before the first request from the batch runs.
     *
     * @var (\Closure($this): void)|null
     */
    protected $before_callback;
    /**
     * The callback to run after a request from the batch succeeds.
     *
     * @var (\Closure($this, int|string, \Illuminate\Http\Client\Response): void)|null
     */
    protected $progress_callback;
    /**
     * The callback to run after a request from the batch fails.
     *
     * @var (\Closure($this, int|string, \Illuminate\Http\Client\Response|\Illuminate\Http\Client\RequestException|\Illuminate\Http\Client\ConnectionException): void)|null
     */
    protected $catch_callback;
    /**
     * The callback to run if all the requests from the batch succeeded.
     *
     * @var (\Closure($this, array<int|string, \Illuminate\Http\Client\Response>): void)|null
     */
    protected $then_callback;
    /**
     * The callback to run after all the requests from the batch finish.
     *
     * @var (\Closure($this, array<int|string, \Illuminate\Http\Client\Response>): void)|null
     */
    protected $finally_callback;
    /**
     * If the batch already was sent.
     *
     * @var bool
     */
    protected $in_progress = false;
    /**
     * The date when the batch was created.
     *
     * @var \Carbon\CarbonImmutable|null
     */
    public $created_at;
    /**
     * The date when the batch finished.
     *
     * @var \Carbon\CarbonImmutable|null
     */
    public $finished_at;
    /**
     * The maximum number of concurrent requests.
     *
     * @var int|null
     */
    protected $concurrency_limit;
    /**
     * Create a new request batch instance.
     */
    public function __construct(?Factory $factory = null)
    {
        $this->factory = $factory ?: new Factory();
        $this->handler = Utils::choose_handler();
        $this->created_at = new Carbon_Immutable();
    }
    /**
     * Add a request to the batch with a key.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     * @throws \Illuminate\Http\Client\BatchInProgressException
     */
    public function as(string $key)
    {
        if ($this->in_progress) {
            throw new Batch_In_Progress_Exception();
        }
        $this->increment_pending_requests();
        return $this->requests[$key] = $this->async_request();
    }
    /**
     * Add a request to the batch with a numeric index.
     *
     * @return \Illuminate\Http\Client\PendingRequest|\GuzzleHttp\Promise\Promise
     *
     * @throws \Illuminate\Http\Client\BatchInProgressException
     */
    public function new_request()
    {
        if ($this->in_progress) {
            throw new Batch_In_Progress_Exception();
        }
        $this->increment_pending_requests();
        return $this->requests[] = $this->async_request();
    }
    /**
     * Register a callback to run before the first request from the batch runs.
     *
     * @param  (\Closure($this): void)  $callback
     */
    public function before(Closure $callback): self
    {
        $this->before_callback = $callback;
        return $this;
    }
    /**
     * Register a callback to run after a request from the batch succeeds.
     *
     * @param  (\Closure($this, int|string, \Illuminate\Http\Client\Response): void)  $callback
     */
    public function progress(Closure $callback): self
    {
        $this->progress_callback = $callback;
        return $this;
    }
    /**
     * Register a callback to run after a request from the batch fails.
     *
     * @param  (\Closure($this, int|string, \Illuminate\Http\Client\Response|\Illuminate\Http\Client\RequestException|\Illuminate\Http\Client\ConnectionException): void)  $callback
     */
    public function catch(Closure $callback): self
    {
        $this->catch_callback = $callback;
        return $this;
    }
    /**
     * Register a callback to run after all the requests from the batch succeed.
     *
     * @param  (\Closure($this, array<int|string, \Illuminate\Http\Client\Response>): void)  $callback
     */
    public function then(Closure $callback): self
    {
        $this->then_callback = $callback;
        return $this;
    }
    /**
     * Register a callback to run after all the requests from the batch finish.
     *
     * @param  (\Closure($this, array<int|string, \Illuminate\Http\Client\Response>): void)  $callback
     */
    public function finally(Closure $callback): self
    {
        $this->finally_callback = $callback;
        return $this;
    }
    /**
     * Set the maximum number of concurrent requests.
     */
    public function concurrency(int $limit): self
    {
        $this->concurrency_limit = $limit;
        return $this;
    }
    /**
     * Defer the batch to run in the background after the current task has finished.
     */
    public function defer(): Deferred_Callback
    {
        return defer(fn(): array => $this->send());
    }
    /**
     * Send all of the requests in the batch.
     *
     * @return array<int|string, \Illuminate\Http\Client\Response|\Illuminate\Http\Client\RequestException>
     */
    public function send(): array
    {
        $this->in_progress = true;
        if ($this->before_callback !== null) {
            call_user_func($this->before_callback, $this);
        }
        $results = [];
        if (!empty($this->requests)) {
            $each_promise_options = ['fulfilled' => function ($result, $key) use (&$results) {
                $results[$key] = $result;
                $this->decrement_pending_requests();
                if ($result instanceof Response && $result->successful()) {
                    if ($this->progress_callback !== null) {
                        call_user_func($this->progress_callback, $this, $key, $result);
                    }
                    return $result;
                }
                if ($result instanceof Response && $result->failed() || $result instanceof Request_Exception || $result instanceof Connection_Exception) {
                    $this->increment_failed_requests();
                    if ($this->catch_callback !== null) {
                        call_user_func($this->catch_callback, $this, $key, $result);
                    }
                }
                return $result;
            }, 'rejected' => function ($reason, $key) {
                $this->decrement_pending_requests();
                if ($reason instanceof Request_Exception || $reason instanceof Connection_Exception) {
                    $this->increment_failed_requests();
                    if ($this->catch_callback !== null) {
                        call_user_func($this->catch_callback, $this, $key, $reason);
                    }
                }
                return $reason;
            }];
            if ($this->concurrency_limit !== null) {
                $each_promise_options['concurrency'] = $this->concurrency_limit;
            }
            $promise_generator = function () {
                foreach ($this->requests as $key => $item) {
                    $promise = $item instanceof Pending_Request ? $item->get_promise() : $item;
                    yield $key => $promise instanceof Lazy_Promise ? $promise->build_promise() : $promise;
                }
            };
            (new Each_Promise($promise_generator(), $each_promise_options))->promise()->wait();
        }
        // Before returning the results, we must ensure that the results are sorted
        // in the same order as the requests were defined, respecting any custom
        // key names that were assigned to this request using the "as" method.
        uksort($results, fn($key1, $key2): int => array_search($key1, array_keys($this->requests), true) <=> array_search($key2, array_keys($this->requests), true));
        if (!$this->has_failures() && $this->then_callback !== null) {
            call_user_func($this->then_callback, $this, $results);
        }
        if ($this->finally_callback !== null) {
            call_user_func($this->finally_callback, $this, $results);
        }
        $this->finished_at = new Carbon_Immutable();
        $this->in_progress = false;
        return $results;
    }
    /**
     * Retrieve a new async pending request.
     */
    protected function async_request(): \Illuminate\Http\Client\Pending_Request
    {
        return $this->factory->set_handler($this->handler)->async();
    }
    /**
     * Get the total number of requests that have been processed by the batch thus far.
     *
     * @return non-negative-int
     */
    public function processed_requests(): int
    {
        return $this->total_requests - $this->pending_requests;
    }
    /**
     * Determine if the batch has finished executing.
     */
    public function finished(): bool
    {
        return !is_null($this->finished_at);
    }
    /**
     * Increment the count of total and pending requests in the batch.
     */
    protected function increment_pending_requests(): void
    {
        $this->total_requests++;
        $this->pending_requests++;
    }
    /**
     * Decrement the count of pending requests in the batch.
     */
    protected function decrement_pending_requests(): void
    {
        $this->pending_requests--;
    }
    /**
     * Determine if the batch has job failures.
     */
    public function has_failures(): bool
    {
        return $this->failed_requests > 0;
    }
    /**
     * Increment the count of failed requests in the batch.
     */
    protected function increment_failed_requests(): void
    {
        $this->failed_requests++;
    }
    /**
     * Get the requests in the batch.
     *
     * @return array<array-key, \Illuminate\Http\Client\PendingRequest>
     */
    public function get_requests(): array
    {
        return $this->requests;
    }
    /**
     * Add a request to the batch with a numeric index.
     *
     * @return \Illuminate\Http\Client\PendingRequest|\GuzzleHttp\Promise\Promise
     *
     * @throws \Illuminate\Http\Client\BatchInProgressException
     */
    public function __call(string $method, array $parameters)
    {
        return $this->new_request()->{$method}(...$parameters);
    }
}