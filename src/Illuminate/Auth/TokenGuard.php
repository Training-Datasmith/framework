<?php

declare(strict_types=1);

namespace Illuminate\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Macroable;

class TokenGuard implements Guard
{
    use GuardHelpers;
    use Macroable;

    /**
     * Create a new authentication guard.
     *
     * @param  string  $inputKey
     * @param  string  $storageKey
     * @param  bool  $hash
     */
    public function __construct(
        UserProvider $provider,
        /**
         * The request instance.
         */
        protected \Illuminate\Http\Request $request,
        /**
         * The name of the query string item from the request containing the API token.
         */
        protected $inputKey = 'api_token',
        /**
         * The name of the token "column" in persistent storage.
         */
        protected $storageKey = 'api_token',
        /**
         * Indicates if the API token is hashed in storage.
         */
        protected $hash = false,
    ) {
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
        if (! is_null($this->user)) {
            return $this->user;
        }

        $user = null;

        $token = $this->getTokenForRequest();

        if (! empty($token)) {
            $user = $this->provider->retrieveByCredentials([
                $this->storageKey => $this->hash ? hash('sha256', $token) : $token,
            ]);
        }

        return $this->user = $user;
    }

    /**
     * Get the token for the current request.
     *
     * @return string|null
     */
    public function getTokenForRequest()
    {
        return $this->request->query($this->inputKey)
            ?: $this->request->input($this->inputKey)
            ?: $this->request->bearerToken()
            ?: $this->request->getPassword();
    }

    /**
     * Validate a user's credentials.
     *
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        if (empty($credentials[$this->inputKey])) {
            return false;
        }

        $credentials = [$this->storageKey => $credentials[$this->inputKey]];

        return (bool) $this->provider->retrieveByCredentials($credentials);
    }

    /**
     * Set the current request instance.
     *
     * @return $this
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;

        return $this;
    }
}
