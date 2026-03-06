<?php

declare(strict_types=1);

namespace Illuminate\View\Compilers\Concerns;

trait CompilesTranslations
{
    /**
     * Compile the lang statements into valid PHP.
     *
     * @param  string|null  $expression
     */
    protected function compileLang($expression): string
    {
        if (is_null($expression)) {
            return '<?php $__env->startTranslation(); ?>';
        }
        if ($expression[1] === '[') {
            return "<?php \$__env->startTranslation{$expression}; ?>";
        }

        return "<?php echo app('translator')->get{$expression}; ?>";
    }

    /**
     * Compile the end-lang statements into valid PHP.
     */
    protected function compileEndlang(): string
    {
        return '<?php echo $__env->renderTranslation(); ?>';
    }

    /**
     * Compile the choice statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileChoice($expression): string
    {
        return "<?php echo app('translator')->choice{$expression}; ?>";
    }
}
