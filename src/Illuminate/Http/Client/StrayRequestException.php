<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

use RuntimeException;
class Stray_Request_Exception extends RuntimeException
{
    public function __construct(string $uri)
    {
        parent::__construct('Attempted request to [' . $uri . '] without a matching fake.');
    }
}