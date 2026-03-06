<?php

namespace Illuminate\Database\Query;

class IndexHint
{
    /**
     * Create a new index hint instance.
     *
     * @param  string  $type
     * @param  string  $index
     */
    public function __construct(
        /**
         * The type of query hint.
         */
        public $type,
        /**
         * The name of the index.
         */
        public $index
    )
    {
    }
}
