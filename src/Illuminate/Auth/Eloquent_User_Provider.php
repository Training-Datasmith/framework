<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\User_Provider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Contracts\Support\Arrayable;
class Eloquent_User_Provider implements User_Provider
{
    /**
     * The callback that may modify the user retrieval queries.
     *
     * @var (\Closure(\Illuminate\Database\Eloquent\Builder<*>):mixed)|null
     */
    protected $query_callback;
    /**
     * Create a new database user provider.
     *
     * @param  string  $model
     */
    public function __construct(
        /**
         * The hasher implementation.
         */
        protected \Illuminate\Contracts\Hashing\Hasher $hasher,
        /**
         * The Eloquent user model.
         */
        protected $model
    )
    {
    }
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return (\Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model)|null
     */
    public function retrieve_by_id($identifier)
    {
        $model = $this->create_model();
        return $this->new_model_query($model)->where($model->get_auth_identifier_name(), $identifier)->first();
    }
    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return (\Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model)|null
     */
    public function retrieve_by_token(
        $identifier,
        #[\Sensitive_Parameter]
        $token
    )
    {
        $model = $this->create_model();
        $retrieved_model = $this->new_model_query($model)->where($model->get_auth_identifier_name(), $identifier)->first();
        if (!$retrieved_model) {
            return;
        }
        $remember_token = $retrieved_model->get_remember_token();
        return $remember_token && hash_equals($remember_token, $token) ? $retrieved_model : null;
    }
    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model  $user
     * @param  string  $token
     */
    public function update_remember_token(
        User_Contract $user,
        #[\Sensitive_Parameter]
        $token
    ): void
    {
        $user->set_remember_token($token);
        $timestamps = $user->timestamps;
        $user->timestamps = false;
        $user->save();
        $user->timestamps = $timestamps;
    }
    /**
     * Retrieve a user by the given credentials.
     *
     * @return (\Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model)|null
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
        // Eloquent User "model" that will be utilized by the Guard instances.
        $query = $this->new_model_query();
        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->where_in($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }
        return $query->first();
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
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model  $user
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
        $user->force_fill([$user->get_auth_password_name() => $this->hasher->make($credentials['password'])])->save();
    }
    /**
     * Get a new query builder for the model instance.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel|null  $model
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    protected function new_model_query($model = null)
    {
        $query = is_null($model) ? $this->create_model()->new_query() : $model->new_query();
        with($query, $this->query_callback);
        return $query;
    }
    /**
     * Create a new instance of the model.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model
     */
    public function create_model()
    {
        $class = '\\' . ltrim($this->model, '\\');
        return new $class();
    }
    /**
     * Gets the hasher implementation.
     */
    public function get_hasher(): \Illuminate\Contracts\Hashing\Hasher
    {
        return $this->hasher;
    }
    /**
     * Sets the hasher implementation.
     *
     * @return $this
     */
    public function set_hasher(Hasher_Contract $hasher): static
    {
        $this->hasher = $hasher;
        return $this;
    }
    /**
     * Gets the name of the Eloquent user model.
     *
     * @return class-string<\Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model>
     */
    public function get_model()
    {
        return $this->model;
    }
    /**
     * Sets the name of the Eloquent user model.
     *
     * @param  class-string<\Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model>  $model
     * @return $this
     */
    public function set_model($model): static
    {
        $this->model = $model;
        return $this;
    }
    /**
     * Get the callback that modifies the query before retrieving users.
     *
     * @return (\Closure(\Illuminate\Database\Eloquent\Builder<*>):mixed)|null
     */
    public function get_query_callback()
    {
        return $this->query_callback;
    }
    /**
     * Sets the callback to modify the query before retrieving users.
     *
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<*>):mixed)|null  $queryCallback
     * @return $this
     */
    public function with_query($query_callback = null): static
    {
        $this->query_callback = $query_callback;
        return $this;
    }
}