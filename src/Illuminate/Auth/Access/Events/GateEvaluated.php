<?php

namespace Illuminate\Auth\Access\Events;

class GateEvaluated
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  string  $ability
     * @param  bool|null  $result
     * @param  array  $arguments
     */
    public function __construct(
        /**
         * The authenticatable model.
         */
        public $user,
        /**
         * The ability being evaluated.
         */
        public $ability,
        /**
         * The result of the evaluation.
         */
        public $result,
        /**
         * The arguments given during evaluation.
         */
        public $arguments
    )
    {
    }
}
