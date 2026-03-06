<?php

declare(strict_types=1);

namespace Illuminate\View\Compilers\Concerns;

trait CompilesRawPhp
{
    /**
     * Compile the raw PHP statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compilePhp($expression): string
    {
        if ($expression) {
            return "<?php {$expression}; ?>";
        }

        return '@php';
    }

    /**
     * Compile the unset statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileUnset($expression): string
    {
        return "<?php unset{$expression}; ?>";
    }
}
