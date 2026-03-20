<?php

declare (strict_types=1);
namespace Illuminate\Http;

class Streamed_Event
{
    /**
     * Create a new streamed event instance.
     */
    public function __construct(
        /**
         * The name of the event.
         */
        public string $event,
        /**
         * The data of the stream.
         */
        public mixed $data
    )
    {
    }
}