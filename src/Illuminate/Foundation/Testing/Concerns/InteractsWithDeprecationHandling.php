<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use ErrorException;
trait Interacts_With_Deprecation_Handling
{
    /**
     * The original deprecation handler.
     *
     * @var callable|null
     */
    protected $original_deprecation_handler;
    /**
     * Restore deprecation handling.
     *
     * @return $this
     */
    protected function with_deprecation_handling()
    {
        if ($this->original_deprecation_handler) {
            set_error_handler(tap($this->original_deprecation_handler, fn(): null => $this->original_deprecation_handler = null));
        }
        return $this;
    }
    /**
     * Disable deprecation handling for the test.
     *
     * @return $this
     */
    protected function without_deprecation_handling()
    {
        if ($this->original_deprecation_handler == null) {
            $this->original_deprecation_handler = set_error_handler(function ($level, $message, $file = '', $line = 0): void {
                if (in_array($level, [E_DEPRECATED, E_USER_DEPRECATED]) || error_reporting() & $level) {
                    throw new ErrorException($message, 0, $level, $file, $line);
                }
            });
        }
        return $this;
    }
}