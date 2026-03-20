<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Support;

interface Has_Once_Hash
{
    /**
     * Compute the hash that should be used to represent the object when given to a function using "once".
     *
     * @return string
     */
    public function once_hash();
}