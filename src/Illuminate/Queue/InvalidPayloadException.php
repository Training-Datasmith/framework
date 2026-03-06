<?php

namespace Illuminate\Queue;

use InvalidArgumentException;

class InvalidPayloadException extends InvalidArgumentException
{
    /**
     * Create a new exception instance.
     *
     * @param  string|null  $message
     * @param  mixed  $value
     */
    public function __construct($message = null, /**
     * The value that failed to decode.
     */
    public $value = null)
    {
        parent::__construct($message ?: json_last_error());
    }
}
