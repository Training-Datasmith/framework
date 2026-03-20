<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Foundation;

interface Caches_Routes
{
    /**
     * Determine if the application routes are cached.
     *
     * @return bool
     */
    public function routes_are_cached();
    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */
    public function get_cached_routes_path();
}