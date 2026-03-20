<?php

declare (strict_types=1);
namespace Illuminate\Console\View\Components\Mutators;

class Ensure_Dynamic_Content_Is_Highlighted
{
    /**
     * Highlight dynamic content within the given string.
     *
     * @param  string  $string
     * @return string
     */
    public function __invoke($string): ?string
    {
        return preg_replace('/\[([^\]]+)\]/', '<options=bold>[$1]</>', (string) $string);
    }
}