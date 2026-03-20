<?php

declare (strict_types=1);
namespace Illuminate\Auth\Events;

use Illuminate\Queue\Serializes_Models;
class Login
{
    use Serializes_Models;
    /**
     * Create a new event instance.
     *
     * @param  string  $guard  The authentication guard name.
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user  The authenticated user.
     * @param  bool  $remember  Indicates if the user should be "remembered".
     */
    public function __construct(public $guard, public $user, public $remember)
    {
    }
}