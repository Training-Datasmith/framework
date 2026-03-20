<?php

declare (strict_types=1);
namespace Illuminate\Console;

trait Prohibitable
{
    /**
     * Indicates if the command should be prohibited from running.
     *
     * @var bool
     */
    protected static $prohibited_from_running = false;
    /**
     * Indicate whether the command should be prohibited from running.
     *
     * @param  bool  $prohibit
     */
    public static function prohibit($prohibit = true): void
    {
        static::$prohibited_from_running = $prohibit;
    }
    /**
     * Determine if the command is prohibited from running and display a warning if so.
     */
    protected function is_prohibited(bool $quiet = false): bool
    {
        if (!static::$prohibited_from_running) {
            return false;
        }
        if (!$quiet) {
            $this->components->warn('This command is prohibited from running in this environment.');
        }
        return true;
    }
}