<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http\Middleware\Concerns;

trait Excludes_Paths
{
    /**
     * Determine if the request has a URI that should be excluded.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function in_except_array($request): bool
    {
        foreach ($this->get_excluded_paths() as $except) {
            if ($except !== '/') {
                $except = trim((string) $except, '/');
            }
            if ($request->full_url_is($except) || $request->is($except)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Get the URIs that should be excluded.
     *
     * @return array
     */
    public function get_excluded_paths()
    {
        return $this->except ?? [];
    }
}