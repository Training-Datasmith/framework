<?php

declare (strict_types=1);
namespace Illuminate\Auth\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Must_Verify_Email;
class Send_Email_Verification_Notification
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        if ($event->user instanceof Must_Verify_Email && !$event->user->has_verified_email()) {
            $event->user->send_email_verification_notification();
        }
    }
}