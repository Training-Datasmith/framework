<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Process;

interface Process_Result
{
    /**
     * Get the original command executed by the process.
     *
     * @return string
     */
    public function command();
    /**
     * Determine if the process was successful.
     *
     * @return bool
     */
    public function successful();
    /**
     * Determine if the process failed.
     *
     * @return bool
     */
    public function failed();
    /**
     * Get the exit code of the process.
     *
     * @return int|null
     */
    public function exit_code();
    /**
     * Get the standard output of the process.
     *
     * @return string
     */
    public function output();
    /**
     * Determine if the output contains the given string.
     *
     * @return bool
     */
    public function see_in_output(string $output);
    /**
     * Get the error output of the process.
     *
     * @return string
     */
    public function error_output();
    /**
     * Determine if the error output contains the given string.
     *
     * @return bool
     */
    public function see_in_error_output(string $output);
    /**
     * Throw an exception if the process failed.
     *
     * @return $this
     */
    public function throw(?callable $callback = null);
    /**
     * Throw an exception if the process failed and the given condition is true.
     *
     * @return $this
     */
    public function throw_if(bool $condition, ?callable $callback = null);
}