<?php

declare (strict_types=1);
namespace Illuminate\Http\Exceptions;

use Symfony\Component\Http_Kernel\Exception\Http_Exception;
class Malformed_Url_Exception extends Http_Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct()
    {
        parent::__construct(400, 'Malformed URL.');
    }
}