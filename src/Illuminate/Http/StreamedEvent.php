<?php

namespace Illuminate\Http;

class StreamedEvent
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
