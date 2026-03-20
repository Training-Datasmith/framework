<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components\Mutators;

class Ensure_Relative_Paths
{
    /**
     * Ensures the given string only contains relative paths.
     *
     * @param  string  $string
     * @return string
     */
    public function __invoke($string)
    {
        if (function_exists('app') && app()->has('path.base')) {
            return str_replace(base_path() . '/', '', $string);
        }
        return $string;
    }
}