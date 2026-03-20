<?php

declare (strict_types=1);
namespace Illuminate\Auth\Events;

class Attempting
{
    /**
     * Create a new event instance.
     *
     * @param  string  $guard  The authentication guard name.
     * @param  array  $credentials  The credentials for the user.
     * @param  bool  $remember  Indicates if the user should be "remembered".
     */
    public function __construct(
        public $guard,
        #[\Sensitive_Parameter]
        public $credentials,
        public $remember
    )
    {
    }
}