<?php

namespace Illuminate\Support;

class HigherOrderTapProxy
{
    /**
     * Create a new tap proxy instance.
     *
     * @param  mixed  $target
     */
    public function __construct(
        /**
         * The target being tapped.
         */
        public $target
    )
    {
    }

    /**
     * Dynamically pass method calls to the target.
     *
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $this->target->{$method}(...$parameters);

        return $this->target;
    }
}
