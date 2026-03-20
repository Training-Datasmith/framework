<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Process;

interface Invoked_Process
{
    /**
     * Get the process ID if the process is still running.
     *
     * @return int|null
     */
    public function id();
    /**
     * Send a signal to the process.
     *
     * @return $this
     */
    public function signal(int $signal);
    /**
     * Determine if the process is still running.
     *
     * @return bool
     */
    public function running();
    /**
     * Get the standard output for the process.
     *
     * @return string
     */
    public function output();
    /**
     * Get the error output for the process.
     *
     * @return string
     */
    public function error_output();
    /**
     * Get the latest standard output for the process.
     *
     * @return string
     */
    public function latest_output();
    /**
     * Get the latest error output for the process.
     *
     * @return string
     */
    public function latest_error_output();
    /**
     * Wait for the process to finish.
     *
     * @return \Illuminate\Process\ProcessResult
     */
    public function wait(?callable $output = null);
    /**
     * Wait until the given callback returns true.
     *
     * @return \Illuminate\Process\ProcessResult
     */
    public function wait_until(?callable $output = null);
}