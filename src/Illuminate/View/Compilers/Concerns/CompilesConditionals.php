<?php

namespace Illuminate\View\Compilers\Concerns;

use Illuminate\Support\Str;

trait CompilesConditionals
{
    /**
     * Identifier for the first case in the switch statement.
     *
     * @var bool
     */
    protected $firstCaseInSwitch = true;

    /**
     * Compile the if-auth statements into valid PHP.
     *
     * @param  string|null  $guard
     */
    protected function compileAuth($guard = null): string
    {
        $guard = is_null($guard) ? '()' : $guard;

        return "<?php if(auth()->guard{$guard}->check()): ?>";
    }

    /**
     * Compile the else-auth statements into valid PHP.
     *
     * @param  string|null  $guard
     */
    protected function compileElseAuth($guard = null): string
    {
        $guard = is_null($guard) ? '()' : $guard;

        return "<?php elseif(auth()->guard{$guard}->check()): ?>";
    }

    /**
     * Compile the end-auth statements into valid PHP.
     */
    protected function compileEndAuth(): string
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the env statements into valid PHP.
     *
     * @param  string  $environments
     */
    protected function compileEnv($environments): string
    {
        return "<?php if(app()->environment{$environments}): ?>";
    }

    /**
     * Compile the end-env statements into valid PHP.
     */
    protected function compileEndEnv(): string
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the production statements into valid PHP.
     */
    protected function compileProduction(): string
    {
        return "<?php if(app()->environment('production')): ?>";
    }

    /**
     * Compile the end-production statements into valid PHP.
     */
    protected function compileEndProduction(): string
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the if-guest statements into valid PHP.
     *
     * @param  string|null  $guard
     */
    protected function compileGuest($guard = null): string
    {
        $guard = is_null($guard) ? '()' : $guard;

        return "<?php if(auth()->guard{$guard}->guest()): ?>";
    }

    /**
     * Compile the else-guest statements into valid PHP.
     *
     * @param  string|null  $guard
     */
    protected function compileElseGuest($guard = null): string
    {
        $guard = is_null($guard) ? '()' : $guard;

        return "<?php elseif(auth()->guard{$guard}->guest()): ?>";
    }

    /**
     * Compile the end-guest statements into valid PHP.
     */
    protected function compileEndGuest(): string
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the has-section statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileHasSection($expression): string
    {
        return "<?php if (! empty(trim(\$__env->yieldContent{$expression}))): ?>";
    }

    /**
     * Compile the has-stack statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileHasStack($expression): string
    {
        return "<?php if (! \$__env->isStackEmpty{$expression}): ?>";
    }

    /**
     * Compile the section-missing statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileSectionMissing($expression): string
    {
        return "<?php if (empty(trim(\$__env->yieldContent{$expression}))): ?>";
    }

    /**
     * Compile the if statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileIf($expression): string
    {
        return "<?php if{$expression}: ?>";
    }

    /**
     * Compile the unless statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileUnless($expression): string
    {
        return "<?php if (! {$expression}): ?>";
    }

    /**
     * Compile the else-if statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileElseif($expression): string
    {
        return "<?php elseif{$expression}: ?>";
    }

    /**
     * Compile the else statements into valid PHP.
     */
    protected function compileElse(): string
    {
        return '<?php else: ?>';
    }

    /**
     * Compile the end-if statements into valid PHP.
     */
    protected function compileEndif(): string
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the end-unless statements into valid PHP.
     */
    protected function compileEndunless(): string
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the if-isset statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileIsset($expression): string
    {
        return "<?php if(isset{$expression}): ?>";
    }

    /**
     * Compile the end-isset statements into valid PHP.
     */
    protected function compileEndIsset(): string
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the switch statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileSwitch($expression): string
    {
        $this->firstCaseInSwitch = true;

        return "<?php switch{$expression}:";
    }

    /**
     * Compile the case statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileCase($expression): string
    {
        if ($this->firstCaseInSwitch) {
            $this->firstCaseInSwitch = false;

            return "case {$expression}: ?>";
        }

        return "<?php case {$expression}: ?>";
    }

    /**
     * Compile the default statements in switch case into valid PHP.
     */
    protected function compileDefault(): string
    {
        return '<?php default: ?>';
    }

    /**
     * Compile the end switch statements into valid PHP.
     */
    protected function compileEndSwitch(): string
    {
        return '<?php endswitch; ?>';
    }

    /**
     * Compile a once block into valid PHP.
     *
     * @param  string|null  $id
     */
    protected function compileOnce($id = null): string
    {
        $id = $id ? $this->stripParentheses($id) : "'".Str::uuid()."'";

        return '<?php if (! $__env->hasRenderedOnce('.$id.')): $__env->markAsRenderedOnce('.$id.'); ?>';
    }

    /**
     * Compile an end-once block into valid PHP.
     */
    public function compileEndOnce(): string
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile a boolean value into a raw true / false value for embedding into HTML attributes or JavaScript.
     *
     * @param  bool  $condition
     */
    protected function compileBool($condition): string
    {
        return "<?php echo ($condition ? 'true' : 'false'); ?>";
    }

    /**
     * Compile a checked block into valid PHP.
     *
     * @param  string  $condition
     */
    protected function compileChecked($condition): string
    {
        return "<?php if{$condition}: echo 'checked'; endif; ?>";
    }

    /**
     * Compile a disabled block into valid PHP.
     *
     * @param  string  $condition
     */
    protected function compileDisabled($condition): string
    {
        return "<?php if{$condition}: echo 'disabled'; endif; ?>";
    }

    /**
     * Compile a required block into valid PHP.
     *
     * @param  string  $condition
     */
    protected function compileRequired($condition): string
    {
        return "<?php if{$condition}: echo 'required'; endif; ?>";
    }

    /**
     * Compile a readonly block into valid PHP.
     *
     * @param  string  $condition
     */
    protected function compileReadonly($condition): string
    {
        return "<?php if{$condition}: echo 'readonly'; endif; ?>";
    }

    /**
     * Compile a selected block into valid PHP.
     *
     * @param  string  $condition
     */
    protected function compileSelected($condition): string
    {
        return "<?php if{$condition}: echo 'selected'; endif; ?>";
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compilePushIf($expression): string
    {
        $parts = explode(',', $this->stripParentheses($expression));

        if (count($parts) > 2) {
            $last = array_pop($parts);

            $parts = [
                implode(',', $parts),
                trim($last),
            ];
        }

        return "<?php if({$parts[0]}): \$__env->startPush({$parts[1]}); ?>";
    }

    /**
     * Compile the else-if push statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileElsePushIf($expression): string
    {
        $parts = explode(',', $this->stripParentheses($expression), 2);

        return "<?php \$__env->stopPush(); elseif({$parts[0]}): \$__env->startPush({$parts[1]}); ?>";
    }

    /**
     * Compile the else push statements into valid PHP.
     *
     * @param  string  $expression
     */
    protected function compileElsePush($expression): string
    {
        return "<?php \$__env->stopPush(); else: \$__env->startPush{$expression}; ?>";
    }

    /**
     * Compile the end-push statements into valid PHP.
     */
    protected function compileEndPushIf(): string
    {
        return '<?php $__env->stopPush(); endif; ?>';
    }
}
