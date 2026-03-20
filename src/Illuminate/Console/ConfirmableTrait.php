<?php

declare (strict_types=1);
namespace Illuminate\Console;

use function Laravel\Prompts\confirm;
trait Confirmable_Trait
{
    /**
     * Confirm before proceeding with the action.
     *
     * This method only asks for confirmation in production.
     *
     * @template TReturn of bool = bool
     *
     * @param  string  $warning
     * @param  (\Closure(): TReturn)|TReturn|null  $callback
     */
    public function confirm_to_proceed($warning = 'Application In Production', $callback = null): bool
    {
        $callback = is_null($callback) ? $this->get_default_confirm_callback() : $callback;
        $should_confirm = value($callback);
        if ($should_confirm) {
            if ($this->has_option('force') && $this->option('force')) {
                return true;
            }
            $this->components->alert($warning);
            $confirmed = confirm('Are you sure you want to run this command?', default: false);
            if (!$confirmed) {
                $this->components->warn('Command cancelled.');
                return false;
            }
        }
        return true;
    }
    /**
     * Get the default confirmation callback.
     *
     * @return \Closure(): bool
     */
    protected function get_default_confirm_callback()
    {
        return fn(): bool => $this->get_laravel()->environment() === 'production';
    }
}