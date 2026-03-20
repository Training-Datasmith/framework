<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Auth;

interface Can_Reset_Password
{
    /**
     * Get the e-mail address where password reset links are sent.
     *
     * @return string
     */
    public function get_email_for_password_reset();
    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function send_password_reset_notification($token);
}