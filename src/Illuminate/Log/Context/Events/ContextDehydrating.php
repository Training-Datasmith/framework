<?php

declare (strict_types=1);
namespace Illuminate\Log\Context\Events;

class Context_Dehydrating
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Log\Context\Repository  $context
     */
    public function __construct(
        /**
         * The context instance.
         */
        public $context
    )
    {
    }
}