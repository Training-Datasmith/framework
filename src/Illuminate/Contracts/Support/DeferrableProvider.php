<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Support;

interface Deferrable_Provider
{
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides();
}