<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Queue;

use InvalidArgumentException;
class Entity_Not_Found_Exception extends InvalidArgumentException
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $type
     * @param  mixed  $id
     */
    public function __construct($type, $id)
    {
        $id = (string) $id;
        parent::__construct("Queueable entity [{$type}] not found for ID [{$id}].");
    }
}