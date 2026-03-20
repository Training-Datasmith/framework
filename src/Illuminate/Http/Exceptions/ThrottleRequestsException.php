<?php

declare (strict_types=1);
namespace Illuminate\Http\Exceptions;

use Symfony\Component\Http_Kernel\Exception\Too_Many_Requests_Http_Exception;
use Throwable;
class Throttle_Requests_Exception extends Too_Many_Requests_Http_Exception
{
    /**
     * Create a new throttle requests exception instance.
     *
     * @param  string  $message
     * @param  int  $code
     */
    public function __construct($message = '', ?Throwable $previous = null, array $headers = [], $code = 0)
    {
        parent::__construct(null, $message, $previous, $code, $headers);
    }
}