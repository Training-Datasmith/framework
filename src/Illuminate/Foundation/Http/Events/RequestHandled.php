<?php

namespace Illuminate\Foundation\Http\Events;

class RequestHandled
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     */
    public function __construct(
        /**
         * The request instance.
         */
        public $request,
        /**
         * The response instance.
         */
        public $response
    )
    {
    }
}
