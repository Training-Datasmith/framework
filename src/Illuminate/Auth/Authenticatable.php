<?php

declare (strict_types=1);
namespace Illuminate\Auth;

trait Authenticatable
{
    /**
     * The column name of the password field using during authentication.
     *
     * @var string
     */
    protected $auth_password_name = 'password';
    /**
     * The column name of the "remember me" token.
     *
     * @var string
     */
    protected $remember_token_name = 'remember_token';
    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function get_auth_identifier_name()
    {
        return $this->get_key_name();
    }
    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function get_auth_identifier()
    {
        return $this->{$this->get_auth_identifier_name()};
    }
    /**
     * Get the unique broadcast identifier for the user.
     *
     * @return mixed
     */
    public function get_auth_identifier_for_broadcasting()
    {
        return $this->get_auth_identifier();
    }
    /**
     * Get the name of the password attribute for the user.
     *
     * @return string
     */
    public function get_auth_password_name()
    {
        return $this->auth_password_name;
    }
    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function get_auth_password()
    {
        return $this->{$this->get_auth_password_name()};
    }
    /**
     * Get the token value for the "remember me" session.
     *
     * @return string|null
     */
    public function get_remember_token()
    {
        if (!empty($this->get_remember_token_name())) {
            return (string) $this->{$this->get_remember_token_name()};
        }
    }
    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string  $value
     */
    public function set_remember_token($value): void
    {
        if (!empty($this->get_remember_token_name())) {
            $this->{$this->get_remember_token_name()} = $value;
        }
    }
    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function get_remember_token_name()
    {
        return $this->remember_token_name;
    }
}