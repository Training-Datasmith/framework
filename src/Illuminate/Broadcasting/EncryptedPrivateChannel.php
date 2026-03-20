<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

class Encrypted_Private_Channel extends Channel
{
    /**
     * Create a new channel instance.
     *
     * @param  string  $name
     */
    public function __construct($name)
    {
        parent::__construct('private-encrypted-' . $name);
    }
}