<?php

declare (strict_types=1);
namespace Illuminate\Http\Exceptions;

use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Throwable;
class Post_Too_Large_Exception extends Http_Exception
{
    /**
     * Create a new "post too large" exception instance.
     *
     * @param  string  $message
     * @param  int  $code
     */
    public function __construct($message = '', ?Throwable $previous = null, array $headers = [], $code = 0)
    {
        parent::__construct(413, $message, $previous, $headers, $code);
    }
}