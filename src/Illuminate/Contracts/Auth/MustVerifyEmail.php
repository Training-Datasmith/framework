<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Auth;

interface Must_Verify_Email
{
    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function has_verified_email();
    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    public function mark_email_as_verified();
    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function send_email_verification_notification();
    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function get_email_for_verification();
}