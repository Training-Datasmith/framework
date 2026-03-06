<?php

declare(strict_types=1);

namespace Illuminate\Support;

/**
 * @template TKey of array-key
 *
 * @template-covariant TValue
 *
 * @mixin \Illuminate\Support\Enumerable<TKey, TValue>
 * @mixin TValue
 */
class HigherOrderCollectionProxy
{
    /**
     * Create a new proxy instance.
     *
     * @param  \Illuminate\Support\Enumerable<TKey, TValue>  $collection
     * @param  string  $method
     */
    public function __construct(
        /**
         * The collection being operated on.
         */
        protected \Illuminate\Support\Enumerable $collection,
        /**
         * The method being proxied.
         */
        protected $method
    ) {
    }

    /**
     * Proxy accessing an attribute onto the collection items.
     */
    public function __get(string $key): mixed
    {
        return $this->collection->{$this->method}(fn ($value) => is_array($value) ? $value[$key] : $value->{$key});
    }

    /**
     * Proxy a method call onto the collection items.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->collection->{$this->method}(fn ($value) => is_string($value)
            ? $value::{$method}(...$parameters)
            : $value->{$method}(...$parameters));
    }
}
