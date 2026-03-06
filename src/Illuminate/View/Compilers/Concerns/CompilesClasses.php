<?php

declare(strict_types=1);

namespace Illuminate\View\Compilers\Concerns;

trait CompilesClasses
{
    /**
     * Compile the conditional class statement into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileClass($expression): string
    {
        $expression = is_null($expression) ? '([])' : $expression;

        return "class=\"<?php echo \Illuminate\Support\Arr::toCssClasses{$expression}; ?>\"";
    }
}
