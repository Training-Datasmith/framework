<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\User_Provider;
use Illuminate\Contracts\Support\Arrayable;
class Database_User_Provider implements User_Provider
{
    /**
     * Create a new database user provider.
     *
     * @param  string  $table
     */
    public function __construct(
        /**
         * The active database connection.
         */
        protected \Illuminate\Database\Connection_Interface $connection,
        /**
         * The hasher implementation.
         */
        protected \Illuminate\Contracts\Hashing\Hasher $hasher,
        /**
         * The table containing the users.
         */
        protected $table
    )
    {
    }
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieve_by_id($identifier)
    {
        $user = $this->connection->table($this->table)->find($identifier);
        return $this->get_generic_user($user);
    }
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
    )
    {
        $user = $this->get_generic_user($this->connection->table($this->table)->find($identifier));
        return $user && $user->get_remember_token() && hash_equals($user->get_remember_token(), $token) ? $user : null;
    }
    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  string  $token
     */
    public function update_remember_token(
        User_Contract $user,
        #[\Sensitive_Parameter]
        $token
    ): void
    {
        $this->connection->table($this->table)->where($user->get_auth_identifier_name(), $user->get_auth_identifier())->update([$user->get_remember_token_name() => $token]);
    }
    /**
     * Retrieve a user by the given credentials.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieve_by_credentials(
        #[\Sensitive_Parameter]
        array $credentials
    )
    {
        $credentials = array_filter($credentials, fn($key): bool => !str_contains((string) $key, 'password'), ARRAY_FILTER_USE_KEY);
        if (empty($credentials)) {
            return;
        }
        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // generic "user" object that will be utilized by the Guard instances.
        $query = $this->connection->table($this->table);
        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->where_in($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }
        // Now we are ready to execute the query to see if we have a user matching
        // the given credentials. If not, we will just return null and indicate
        // that there are no matching users from the given credential arrays.
        $user = $query->first();
        return $this->get_generic_user($user);
    }
    /**
     * Get the generic user.
     *
     * @param  mixed  $user
     * @return \Illuminate\Auth\GenericUser|null
     */
    protected function get_generic_user($user)
    {
        if (!is_null($user)) {
            return new Generic_User((array) $user);
        }
    }
    /**
     * Validate a user against the given credentials.
     *
     * @return bool
     */
    public function validate_credentials(
        User_Contract $user,
        #[\Sensitive_Parameter]
        array $credentials
    )
    {
        if (is_null($plain = $credentials['password'])) {
            return false;
        }
        if (is_null($hashed = $user->get_auth_password())) {
            return false;
        }
        return $this->hasher->check($plain, $hashed);
    }
    /**
     * Rehash the user's password if required and supported.
     */
    public function rehash_password_if_required(
        User_Contract $user,
        #[\Sensitive_Parameter]
        array $credentials,
        bool $force = false
    ): void
    {
        if (!$this->hasher->needs_rehash($user->get_auth_password()) && !$force) {
            return;
        }
        $this->connection->table($this->table)->where($user->get_auth_identifier_name(), $user->get_auth_identifier())->update([$user->get_auth_password_name() => $this->hasher->make($credentials['password'])]);
    }
}