<?php

namespace Illuminate\Database;

use RuntimeException;

class MultipleRecordsFoundException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  int  $count
     * @param  int  $code
     * @param  \Throwable|null  $previous
     */
    public function __construct(/**
     * The number of records found.
     */
    public $count, $code = 0, $previous = null)
    {
        parent::__construct("{$this->count} records were found.", $code, $previous);
    }

    /**
     * Get the number of records found.
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }
}
