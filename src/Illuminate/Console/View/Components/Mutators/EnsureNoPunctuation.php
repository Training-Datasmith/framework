<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components\Mutators;

use Illuminate\Support\Stringable;
class Ensure_No_Punctuation
{
    /**
     * Ensures the given string does not end with punctuation.
     *
     * @param  string  $string
     * @return string
     */
    public function __invoke($string)
    {
        if ((new Stringable($string))->ends_with(['.', '?', '!', ':'])) {
            return substr_replace($string, '', -1);
        }
        return $string;
    }
}