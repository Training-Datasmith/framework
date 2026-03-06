<?php

namespace Illuminate\View\Compilers\Concerns;

trait CompilesStyles
{
    /**
     * Compile the conditional style statement into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileStyle($expression): string
    {
        $expression = is_null($expression) ? '([])' : $expression;

        return "style=\"<?php echo \Illuminate\Support\Arr::toCssStyles{$expression} ?>\"";
    }
}
