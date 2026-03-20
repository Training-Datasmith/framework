<?php

declare (strict_types=1);
namespace Illuminate\Auth\Passwords;

use Illuminate\Contracts\Auth\Can_Reset_Password as CanResetPasswordContract;
interface Token_Repository_Interface
{
    /**
     * Create a new token.
     *
     * @return string
     */
    public function create(Can_Reset_Password_Contract $user);
    /**
     * Determine if a token record exists and is valid.
     *
     * @param  string  $token
     * @return bool
     */
    public function exists(
        Can_Reset_Password_Contract $user,
        #[\Sensitive_Parameter]
        $token
    );
    /**
     * Determine if the given user recently created a password reset token.
     *
     * @return bool
     */
    public function recently_created_token(Can_Reset_Password_Contract $user);
    /**
     * Delete a token record.
     *
     * @return void
     */
    public function delete(Can_Reset_Password_Contract $user);
    /**
     * Delete expired tokens.
     *
     * @return void
     */
    public function delete_expired();
}