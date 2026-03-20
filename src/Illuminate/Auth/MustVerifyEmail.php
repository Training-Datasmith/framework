<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Illuminate\Auth\Notifications\Verify_Email;
trait Must_Verify_Email
{
    /**
     * Determine if the user has verified their email address.
     */
    public function has_verified_email(): bool
    {
        return !is_null($this->email_verified_at);
    }
    /**
     * Mark the user's email as verified.
     *
     * @return bool
     */
    public function mark_email_as_verified()
    {
        return $this->force_fill(['email_verified_at' => $this->fresh_timestamp()])->save();
    }
    /**
     * Mark the user's email as unverified.
     *
     * @return bool
     */
    public function mark_email_as_unverified()
    {
        return $this->force_fill(['email_verified_at' => null])->save();
    }
    /**
     * Send the email verification notification.
     */
    public function send_email_verification_notification(): void
    {
        $this->notify(new Verify_Email());
    }
    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function get_email_for_verification()
    {
        return $this->email;
    }
}