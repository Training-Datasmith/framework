<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Auth;

interface User_Provider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieve_by_id($identifier);
    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieve_by_token(
        $identifier,
        #[\Sensitive_Parameter]
        $token
    );
    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  string  $token
     * @return void
     */
    public function update_remember_token(
        Authenticatable $user,
        #[\Sensitive_Parameter]
        $token
    );
    /**
     * Retrieve a user by the given credentials.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieve_by_credentials(
        #[\Sensitive_Parameter]
        array $credentials
    );
    /**
     * Validate a user against the given credentials.
     *
     * @return bool
     */
    public function validate_credentials(
        Authenticatable $user,
        #[\Sensitive_Parameter]
        array $credentials
    );
    /**
     * Rehash the user's password if required and supported.
     *
     * @return void
     */
    public function rehash_password_if_required(
        Authenticatable $user,
        #[\Sensitive_Parameter]
        array $credentials,
        bool $force = false
    );
}