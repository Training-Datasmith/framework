<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\User_Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Macroable;
class Token_Guard implements Guard
{
    use Guard_Helpers;
    use Macroable;
    /**
     * Create a new authentication guard.
     *
     * @param  string  $inputKey
     * @param  string  $storageKey
     * @param  bool  $hash
     */
    public function __construct(
        User_Provider $provider,
        /**
         * The request instance.
         */
        protected \Illuminate\Http\Request $request,
        /**
         * The name of the query string item from the request containing the API token.
         */
        protected $input_key = 'api_token',
        /**
         * The name of the token "column" in persistent storage.
         */
        protected $storage_key = 'api_token',
        /**
         * Indicates if the API token is hashed in storage.
         */
        protected $hash = false
    )
    {
        $this->provider = $provider;
    }
    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        // If we've already retrieved the user for the current request we can just
        // return it back immediately. We do not want to fetch the user data on
        // every call to this method because that would be tremendously slow.
        if (!is_null($this->user)) {
            return $this->user;
        }
        $user = null;
        $token = $this->get_token_for_request();
        if (!empty($token)) {
            $user = $this->provider->retrieve_by_credentials([$this->storage_key => $this->hash ? hash('sha256', $token) : $token]);
        }
        return $this->user = $user;
    }
    /**
     * Get the token for the current request.
     *
     * @return string|null
     */
    public function get_token_for_request()
    {
        return (($this->request->query($this->input_key) ?: $this->request->input($this->input_key)) ?: $this->request->bearer_token()) ?: $this->request->get_password();
    }
    /**
     * Validate a user's credentials.
     *
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        if (empty($credentials[$this->input_key])) {
            return false;
        }
        $credentials = [$this->storage_key => $credentials[$this->input_key]];
        return (bool) $this->provider->retrieve_by_credentials($credentials);
    }
    /**
     * Set the current request instance.
     *
     * @return $this
     */
    public function set_request(Request $request): static
    {
        $this->request = $request;
        return $this;
    }
}