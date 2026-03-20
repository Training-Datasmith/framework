<?php

declare (strict_types=1);
namespace Illuminate\Http\Client\Events;

use Illuminate\Http\Client\Request;
class Request_Sending
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Client\Request
     */
    public $request;
    /**
     * Create a new event instance.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}