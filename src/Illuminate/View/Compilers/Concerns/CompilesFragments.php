<?php

namespace Illuminate\View\Compilers\Concerns;

trait CompilesFragments
{
    /**
     * The last compiled fragment.
     *
     * @var string
     */
    protected $lastFragment;

    /**
     * Compile the fragment statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileFragment($expression): string
    {
        $this->lastFragment = trim($expression, "()'\" ");

        return "<?php \$__env->startFragment{$expression}; ?>";
    }

    /**
     * Compile the end-fragment statements into valid PHP.
     */
    protected function compileEndfragment(): string
    {
        return '<?php echo $__env->stopFragment(); ?>';
    }
}
