<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Auth;

interface Authenticatable
{
    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function get_auth_identifier_name();
    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function get_auth_identifier();
    /**
     * Get the name of the password attribute for the user.
     *
     * @return string
     */
    public function get_auth_password_name();
    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function get_auth_password();
    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function get_remember_token();
    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string  $value
     * @return void
     */
    public function set_remember_token($value);
    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function get_remember_token_name();
}