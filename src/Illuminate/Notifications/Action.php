<?php

declare(strict_types=1);

namespace Illuminate\Notifications;

class Action
{
    /**
     * Create a new action instance.
     *
     * @param  string  $text
     * @param  string  $url
     */
    public function __construct(
        /**
         * The action text.
         */
        public $text,
        /**
         * The action URL.
         */
        public $url
    ) {
    }
}
