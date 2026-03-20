<?php

declare(strict_types=1);

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
    ) {
    }

    /**
     * Dynamically pass method calls to the target.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $this->target->{$method}(...$parameters);

        return $this->target;
    }
}
