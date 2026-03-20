<?php

declare (strict_types=1);
namespace Illuminate\Auth\Events;

use Illuminate\Queue\Serializes_Models;
class Registered
{
    use Serializes_Models;
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user  The authenticated user.
     */
    public function __construct(public $user)
    {
    }
}