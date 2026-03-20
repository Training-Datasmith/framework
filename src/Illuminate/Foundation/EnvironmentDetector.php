<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Closure;
class Environment_Detector
{
    /**
     * Detect the application's current environment.
     *
     * @param  array|null  $consoleArgs
     * @return string
     */
    public function detect(Closure $callback, $console_args = null)
    {
        if ($console_args) {
            return $this->detect_console_environment($callback, $console_args);
        }
        return $this->detect_web_environment($callback);
    }
    /**
     * Set the application environment for a web request.
     *
     * @return string
     */
    protected function detect_web_environment(Closure $callback)
    {
        return $callback();
    }
    /**
     * Set the application environment from command-line arguments.
     *
     * @return string
     */
    protected function detect_console_environment(Closure $callback, array $args)
    {
        // First we will check if an environment argument was passed via console arguments
        // and if it was that automatically overrides as the environment. Otherwise, we
        // will check the environment as a "web" request like a typical HTTP request.
        if (!is_null($value = $this->get_environment_argument($args))) {
            return $value;
        }
        return $this->detect_web_environment($callback);
    }
    /**
     * Get the environment argument from the console.
     *
     * @return string|null
     */
    protected function get_environment_argument(array $args)
    {
        foreach ($args as $i => $value) {
            if ($value === '--env') {
                return $args[$i + 1] ?? null;
            }
            if (str_starts_with((string) $value, '--env=')) {
                return head(array_slice(explode('=', (string) $value), 1));
            }
        }
    }
}