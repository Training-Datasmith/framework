<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Support\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Service_Provider;
class Auth_Service_Provider extends Service_Provider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [];
    /**
     * Register the application's policies.
     */
    public function register(): void
    {
        $this->booting(function (): void {
            $this->register_policies();
        });
    }
    /**
     * Register the application's policies.
     */
    public function register_policies(): void
    {
        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
    /**
     * Get the policies defined on the provider.
     *
     * @return array<class-string, class-string>
     */
    public function policies()
    {
        return $this->policies;
    }
}