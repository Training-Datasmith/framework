<?php

declare (strict_types=1);
namespace Illuminate\Auth\Passwords;

use Illuminate\Auth\Notifications\Reset_Password as ResetPasswordNotification;
trait Can_Reset_Password
{
    /**
     * Get the e-mail address where password reset links are sent.
     *
     * @return string
     */
    public function get_email_for_password_reset()
    {
        return $this->email;
    }
    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function send_password_reset_notification(
        #[\Sensitive_Parameter]
        $token
    ): void
    {
        $this->notify(new Reset_Password_Notification($token));
    }
}