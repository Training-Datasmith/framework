<?php

declare (strict_types=1);
namespace Illuminate\Encryption;

use RuntimeException;
class Missing_App_Key_Exception extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $message
     */
    public function __construct($message = 'No application encryption key has been specified.')
    {
        parent::__construct($message);
    }
}