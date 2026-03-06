<?php

namespace Illuminate\Database\Eloquent;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class HigherOrderBuilderProxy
{
    /**
     * Create a new proxy instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @param  string  $method
     */
    public function __construct(
        /**
         * The collection being operated on.
         */
        protected \Illuminate\Database\Eloquent\Builder $builder,
        /**
         * The method being proxied.
         */
        protected $method
    )
    {
    }

    /**
     * Proxy a scope call onto the query builder.
     *
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->builder->{$this->method}(fn($value) => $value->{$method}(...$parameters));
    }
}
