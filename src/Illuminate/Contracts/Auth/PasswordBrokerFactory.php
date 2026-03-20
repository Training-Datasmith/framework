<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Auth;

interface Password_Broker_Factory
{
    /**
     * Get a password broker instance by name.
     *
     * @param  string|null  $name
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker($name = null);
}