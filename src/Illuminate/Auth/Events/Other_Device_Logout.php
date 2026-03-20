<?php

declare (strict_types=1);
namespace Illuminate\Auth\Events;

use Illuminate\Queue\Serializes_Models;
class Other_Device_Logout
{
    use Serializes_Models;
    /**
     * Create a new event instance.
     *
     * @param  string  $guard  The authentication guard name.
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user  \Illuminate\Contracts\Auth\Authenticatable
     */
    public function __construct(public $guard, public $user)
    {
    }
}