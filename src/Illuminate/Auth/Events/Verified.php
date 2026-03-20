<?php

declare (strict_types=1);
namespace Illuminate\Auth\Events;

use Illuminate\Queue\Serializes_Models;
class Verified
{
    use Serializes_Models;
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Contracts\Auth\MustVerifyEmail  $user  The verified user.
     */
    public function __construct(public $user)
    {
    }
}