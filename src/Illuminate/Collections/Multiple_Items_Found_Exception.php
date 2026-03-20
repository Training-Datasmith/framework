<?php

declare (strict_types=1);
namespace Illuminate\Support;

use RuntimeException;
class Multiple_Items_Found_Exception extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  int  $count
     * @param  int  $code
     * @param  \Throwable|null  $previous
     */
    public function __construct(
        /**
         * The number of items found.
         */
        public $count,
        $code = 0,
        $previous = null
    )
    {
        parent::__construct("{$this->count} items were found.", $code, $previous);
    }
    /**
     * Get the number of items found.
     *
     * @return int
     */
    public function get_count()
    {
        return $this->count;
    }
}