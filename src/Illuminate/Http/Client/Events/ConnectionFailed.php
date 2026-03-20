<?php

declare (strict_types=1);
namespace Illuminate\Http\Client\Events;

use Illuminate\Http\Client\Connection_Exception;
use Illuminate\Http\Client\Request;
class Connection_Failed
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Client\Request
     */
    public $request;
    /**
     * The exception instance.
     *
     * @var \Illuminate\Http\Client\ConnectionException
     */
    public $exception;
    /**
     * Create a new event instance.
     */
    public function __construct(Request $request, Connection_Exception $exception)
    {
        $this->request = $request;
        $this->exception = $exception;
    }
}