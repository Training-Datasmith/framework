<?php

declare(strict_types=1);

namespace Illuminate\Auth;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class GenericUser implements UserContract
{
    /**
     * Create a new generic User object.
     */
    public function __construct(
        /**
         * All of the user's attributes.
         */
        protected array $attributes
    ) {
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->attributes[$this->getAuthIdentifierName()];
    }

    /**
     * Get the name of the password attribute for the user.
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->attributes[$this->getAuthPasswordName()];
    }

    /**
     * Get the "remember me" token value.
     *
     * @return string
     */
    public function getRememberToken()
    {
        return $this->attributes[$this->getRememberTokenName()];
    }

    /**
     * Set the "remember me" token value.
     *
     * @param  string  $value
     */
    public function setRememberToken($value): void
    {
        $this->attributes[$this->getRememberTokenName()] = $value;
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Dynamically access the user's attributes.
     */
    public function __get(string $key): mixed
    {
        return $this->attributes[$key];
    }

    /**
     * Dynamically set an attribute on the user.
     *
     * @return void
     */
    public function __set(string $key, mixed $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Dynamically check if a value is set on the user.
     *
     * @return bool
     */
    public function __isset(string $key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Dynamically unset a value on the user.
     *
     * @return void
     */
    public function __unset(string $key)
    {
        unset($this->attributes[$key]);
    }
}
