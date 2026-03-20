<?php

declare (strict_types=1);
namespace Illuminate\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\User_Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Macroable;
class Request_Guard implements Guard
{
    use Guard_Helpers;
    use Macroable;
    /**
     * The guard callback.
     *
     * @var callable
     */
    protected $callback;
    /**
     * Create a new authentication guard.
     */
    public function __construct(
        callable $callback,
        /**
         * The request instance.
         */
        protected \Illuminate\Http\Request $request,
        ?User_Provider $provider = null
    )
    {
        $this->callback = $callback;
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
        return $this->user = call_user_func($this->callback, $this->request, $this->get_provider());
    }
    /**
     * Validate a user's credentials.
     */
    public function validate(
        #[\Sensitive_Parameter]
        array $credentials = []
    ): bool
    {
        return !is_null((new static($this->callback, $credentials['request'], $this->get_provider()))->user());
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