<?php

declare (strict_types=1);
namespace Illuminate\Log\Context;

use __PHP_Incomplete_Class;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model_Not_Found_Exception;
use Illuminate\Log\Context\Events\Context_Dehydrating as Dehydrating;
use Illuminate\Log\Context\Events\Context_Hydrated as Hydrated;
use Illuminate\Queue\Serializes_Models;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use RuntimeException;
use Throwable;
class Repository
{
    use Conditionable;
    use Macroable;
    use Serializes_Models;
    /**
     * The contextual data.
     *
     * @var array<string, mixed>
     */
    protected $data = [];
    /**
     * The hidden contextual data.
     *
     * @var array<string, mixed>
     */
    protected $hidden = [];
    /**
     * The callback that should handle unserialize exceptions.
     *
     * @var callable|null
     */
    protected static $handle_unserialize_exceptions_using;
    /**
     * Create a new Context instance.
     */
    public function __construct(
        /**
         * The event dispatcher instance.
         */
        protected \Illuminate\Contracts\Events\Dispatcher $events
    )
    {
    }
    /**
     * Determine if the given key exists.
     *
     * @param  string  $key
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->data);
    }
    /**
     * Determine if the given key is missing.
     *
     * @param  string  $key
     */
    public function missing($key): bool
    {
        return !$this->has($key);
    }
    /**
     * Determine if the given key exists within the hidden context data.
     *
     * @param  string  $key
     */
    public function has_hidden($key): bool
    {
        return array_key_exists($key, $this->hidden);
    }
    /**
     * Determine if the given key is missing within the hidden context data.
     *
     * @param  string  $key
     */
    public function missing_hidden($key): bool
    {
        return !$this->has_hidden($key);
    }
    /**
     * Retrieve all the context data.
     *
     * @return array<string, mixed>
     */
    public function all()
    {
        return $this->data;
    }
    /**
     * Retrieve all the hidden context data.
     *
     * @return array<string, mixed>
     */
    public function all_hidden()
    {
        return $this->hidden;
    }
    /**
     * Retrieve the given key's value.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? value($default);
    }
    /**
     * Retrieve the given key's hidden value.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function get_hidden(string $key, $default = null)
    {
        return $this->hidden[$key] ?? value($default);
    }
    /**
     * Retrieve the given key's value and then forget it.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function pull(string $key, $default = null)
    {
        return tap($this->get($key, $default), function () use ($key): void {
            $this->forget($key);
        });
    }
    /**
     * Retrieve the given key's hidden value and then forget it.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function pull_hidden(string $key, $default = null)
    {
        return tap($this->get_hidden($key, $default), function () use ($key): void {
            $this->forget_hidden($key);
        });
    }
    /**
     * Retrieve only the values of the given keys.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function only($keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }
    /**
     * Retrieve only the hidden values of the given keys.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function only_hidden($keys): array
    {
        return array_intersect_key($this->hidden, array_flip($keys));
    }
    /**
     * Retrieve all values except those with the given keys.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function except($keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }
    /**
     * Retrieve all hidden values except those with the given keys.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function except_hidden($keys): array
    {
        return array_diff_key($this->hidden, array_flip($keys));
    }
    /**
     * Add a context value.
     *
     * @param  string|array<string, mixed>  $key
     * @param  mixed  $value
     * @return $this
     */
    public function add($key, $value = null): static
    {
        $this->data = array_merge($this->data, is_array($key) ? $key : [$key => $value]);
        return $this;
    }
    /**
     * Add a hidden context value.
     *
     * @param  string|array<string, mixed>  $key
     * @param  mixed  $value
     * @return $this
     */
    public function add_hidden(
        $key,
        #[\Sensitive_Parameter]
        $value = null
    ): static
    {
        $this->hidden = array_merge($this->hidden, is_array($key) ? $key : [$key => $value]);
        return $this;
    }
    /**
     * Add a context value if it does not exist yet, and return the value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function remember($key, $value)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        return tap(value($value), function ($value) use ($key): void {
            $this->add($key, $value);
        });
    }
    /**
     * Add a hidden context value if it does not exist yet, and return the value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function remember_hidden(
        $key,
        #[\Sensitive_Parameter]
        $value
    )
    {
        if ($this->has_hidden($key)) {
            return $this->get_hidden($key);
        }
        return tap(value($value), function ($value) use ($key): void {
            $this->add_hidden($key, $value);
        });
    }
    /**
     * Forget the given context key.
     *
     * @param  string|array<int, string>  $key
     * @return $this
     */
    public function forget($key): static
    {
        foreach ((array) $key as $k) {
            unset($this->data[$k]);
        }
        return $this;
    }
    /**
     * Forget the given hidden context key.
     *
     * @param  string|array<int, string>  $key
     * @return $this
     */
    public function forget_hidden($key): static
    {
        foreach ((array) $key as $k) {
            unset($this->hidden[$k]);
        }
        return $this;
    }
    /**
     * Add a context value if it does not exist yet.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function add_if($key, $value): static
    {
        if (!$this->has($key)) {
            $this->add($key, $value);
        }
        return $this;
    }
    /**
     * Add a hidden context value if it does not exist yet.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function add_hidden_if(
        $key,
        #[\Sensitive_Parameter]
        $value
    ): static
    {
        if (!$this->has_hidden($key)) {
            $this->add_hidden($key, $value);
        }
        return $this;
    }
    /**
     * Push the given values onto the key's stack.
     *
     * @param  string  $key
     * @param  mixed  ...$values
     * @return $this
     *
     * @throws \RuntimeException
     */
    public function push($key, ...$values): static
    {
        if (!$this->is_stackable($key)) {
            throw new RuntimeException("Unable to push value onto context stack for key [{$key}].");
        }
        $this->data[$key] = [...$this->data[$key] ?? [], ...$values];
        return $this;
    }
    /**
     * Pop the latest value from the key's stack.
     *
     * @param  string  $key
     *
     * @throws \RuntimeException
     */
    public function pop($key): mixed
    {
        if (!$this->is_stackable($key) || !count($this->data[$key])) {
            throw new RuntimeException("Unable to pop value from context stack for key [{$key}].");
        }
        return array_pop($this->data[$key]);
    }
    /**
     * Push the given hidden values onto the key's stack.
     *
     * @param  string  $key
     * @param  mixed  ...$values
     * @return $this
     *
     * @throws \RuntimeException
     */
    public function push_hidden($key, ...$values): static
    {
        if (!$this->is_hidden_stackable($key)) {
            throw new RuntimeException("Unable to push value onto hidden context stack for key [{$key}].");
        }
        $this->hidden[$key] = [...$this->hidden[$key] ?? [], ...$values];
        return $this;
    }
    /**
     * Pop the latest hidden value from the key's stack.
     *
     * @param  string  $key
     *
     * @throws \RuntimeException
     */
    public function pop_hidden($key): mixed
    {
        if (!$this->is_hidden_stackable($key) || !count($this->hidden[$key])) {
            throw new RuntimeException("Unable to pop value from hidden context stack for key [{$key}].");
        }
        return array_pop($this->hidden[$key]);
    }
    /**
     * Increment a context counter.
     *
     * @return $this
     */
    public function increment(string $key, int $amount = 1): static
    {
        $this->add($key, (int) $this->get($key, 0) + $amount);
        return $this;
    }
    /**
     * Decrement a context counter.
     *
     * @return $this
     */
    public function decrement(string $key, int $amount = 1): static
    {
        return $this->increment($key, $amount * -1);
    }
    /**
     * Determine if the given value is in the given stack.
     *
     *
     * @throws \RuntimeException
     */
    public function stack_contains(string $key, mixed $value, bool $strict = false): bool
    {
        if (!$this->is_stackable($key)) {
            throw new RuntimeException("Given key [{$key}] is not a stack.");
        }
        if (!array_key_exists($key, $this->data)) {
            return false;
        }
        if ($value instanceof Closure) {
            return (new Collection($this->data[$key]))->contains($value);
        }
        return in_array($value, $this->data[$key], $strict);
    }
    /**
     * Determine if the given value is in the given hidden stack.
     *
     *
     * @throws \RuntimeException
     */
    public function hidden_stack_contains(string $key, mixed $value, bool $strict = false): bool
    {
        if (!$this->is_hidden_stackable($key)) {
            throw new RuntimeException("Given key [{$key}] is not a stack.");
        }
        if (!array_key_exists($key, $this->hidden)) {
            return false;
        }
        if ($value instanceof Closure) {
            return (new Collection($this->hidden[$key]))->contains($value);
        }
        return in_array($value, $this->hidden[$key], $strict);
    }
    /**
     * Determine if a given key can be used as a stack.
     */
    protected function is_stackable(string $key): bool
    {
        if (!$this->has($key)) {
            return true;
        }
        return is_array($this->data[$key]) && array_is_list($this->data[$key]);
    }
    /**
     * Determine if a given key can be used as a hidden stack.
     */
    protected function is_hidden_stackable(string $key): bool
    {
        if (!$this->has_hidden($key)) {
            return true;
        }
        return is_array($this->hidden[$key]) && array_is_list($this->hidden[$key]);
    }
    /**
     * @template TReturn of mixed
     *
     * Run the callback function with the given context values and restore the original context state when complete.
     *
     * @param  (callable(): TReturn)  $callback
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $hidden
     * @return TReturn
     *
     * @throws \Throwable
     */
    public function scope(callable $callback, array $data = [], array $hidden = [])
    {
        $data_before = $this->data;
        $hidden_before = $this->hidden;
        if ($data !== []) {
            $this->add($data);
        }
        if ($hidden !== []) {
            $this->add_hidden($hidden);
        }
        try {
            return $callback();
        } finally {
            $this->data = $data_before;
            $this->hidden = $hidden_before;
        }
    }
    /**
     * Determine if the repository is empty.
     */
    public function is_empty(): bool
    {
        return $this->all() === [] && $this->all_hidden() === [];
    }
    /**
     * Execute the given callback when context is about to be dehydrated.
     *
     * @param  (callable(static): void)  $callback
     * @return $this
     */
    public function dehydrating($callback): static
    {
        $this->events->listen(fn(Dehydrating $event) => $callback($event->context));
        return $this;
    }
    /**
     * Execute the given callback when context has been hydrated.
     *
     * @param  (callable(static): void)  $callback
     * @return $this
     */
    public function hydrated($callback): static
    {
        $this->events->listen(fn(Hydrated $event) => $callback($event->context));
        return $this;
    }
    /**
     * Handle unserialize exceptions using the given callback.
     *
     * @param  callable|null  $callback
     */
    public function handle_unserialize_exceptions_using($callback): static
    {
        static::$handle_unserialize_exceptions_using = $callback;
        return $this;
    }
    /**
     * Flush all context data.
     *
     * @return $this
     */
    public function flush(): static
    {
        $this->data = [];
        $this->hidden = [];
        return $this;
    }
    /**
     * Dehydrate the context data.
     *
     * @internal
     */
    public function dehydrate(): ?array
    {
        $instance = (new static($this->events))->add($this->all())->add_hidden($this->all_hidden());
        $instance->events->dispatch(new Dehydrating($instance));
        $serialize = fn($value): string => serialize($instance->get_serialized_property_value($value, withRelations: false));
        return $instance->is_empty() ? null : ['data' => array_map($serialize, $instance->all()), 'hidden' => array_map($serialize, $instance->all_hidden())];
    }
    /**
     * Hydrate the context instance.
     *
     * @internal
     *
     * @param  ?array  $context
     * @return $this
     *
     * @throws \RuntimeException
     */
    public function hydrate(array $context): static
    {
        $unserialize = function ($value, $key, $hidden) {
            try {
                return tap($this->get_restored_property_value(unserialize($value)), function ($value): void {
                    if ($value instanceof __PHP_Incomplete_Class) {
                        throw new RuntimeException('Value is incomplete class: ' . json_encode($value));
                    }
                });
            } catch (Throwable $e) {
                if (static::$handle_unserialize_exceptions_using !== null) {
                    return (static::$handle_unserialize_exceptions_using)($e, $key, $value, $hidden);
                }
                if ($e instanceof Model_Not_Found_Exception) {
                    if (function_exists('report')) {
                        report($e);
                    }
                    return null;
                }
                throw $e;
            }
        };
        [$data, $hidden] = [(new Collection($context['data'] ?? []))->map(fn($value, $key) => $unserialize($value, $key, false))->all(), (new Collection($context['hidden'] ?? []))->map(fn($value, $key) => $unserialize($value, $key, true))->all()];
        $this->events->dispatch(new Hydrated($this->flush()->add($data)->add_hidden($hidden)));
        return $this;
    }
}