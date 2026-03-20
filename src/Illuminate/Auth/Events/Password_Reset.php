<?php

declare (strict_types=1);
namespace Illuminate\Auth\Events;

use Illuminate\Queue\Serializes_Models;
class Password_Reset
{
    use Serializes_Models;
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user  The user.
     */
    public function __construct(public $user)
    {
    }
}