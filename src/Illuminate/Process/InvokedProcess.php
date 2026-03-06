<?php

namespace Illuminate\Process;

use Illuminate\Contracts\Process\InvokedProcess as InvokedProcessContract;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process;

class InvokedProcess implements InvokedProcessContract
{
    /**
     * Create a new invoked process instance.
     */
    public function __construct(
        /**
         * The underlying process instance.
         */
        protected \Symfony\Component\Process\Process $process
    )
    {
    }

    /**
     * Get the process ID if the process is still running.
     */
    public function id(): ?int
    {
        return $this->process->getPid();
    }

    /**
     * Get the command line for the process.
     */
    public function command(): string
    {
        return $this->process->getCommandLine();
    }

    /**
     * Send a signal to the process.
     *
     * @return $this
     */
    public function signal(int $signal): static
    {
        $this->process->signal($signal);

        return $this;
    }

    /**
     * Stop the process if it is still running.
     */
    public function stop(float $timeout = 10, ?int $signal = null): ?int
    {
        return $this->process->stop($timeout, $signal);
    }

    /**
     * Determine if the process is still running.
     */
    public function running(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * Get the standard output for the process.
     */
    public function output(): string
    {
        return $this->process->getOutput();
    }

    /**
     * Get the error output for the process.
     */
    public function errorOutput(): string
    {
        return $this->process->getErrorOutput();
    }

    /**
     * Get the latest standard output for the process.
     */
    public function latestOutput(): string
    {
        return $this->process->getIncrementalOutput();
    }

    /**
     * Get the latest error output for the process.
     */
    public function latestErrorOutput(): string
    {
        return $this->process->getIncrementalErrorOutput();
    }

    /**
     * Ensure that the process has not timed out.
     *
     *
     * @throws \Illuminate\Process\Exceptions\ProcessTimedOutException
     */
    public function ensureNotTimedOut(): void
    {
        try {
            $this->process->checkTimeout();
        } catch (SymfonyTimeoutException $e) {
            throw new ProcessTimedOutException($e, new ProcessResult($this->process));
        }
    }

    /**
     * Wait for the process to finish.
     *
     *
     * @throws \Illuminate\Process\Exceptions\ProcessTimedOutException
     */
    public function wait(?callable $output = null): \Illuminate\Process\ProcessResult
    {
        try {
            $this->process->wait($output);

            return new ProcessResult($this->process);
        } catch (SymfonyTimeoutException $e) {
            throw new ProcessTimedOutException($e, new ProcessResult($this->process));
        }
    }

    /**
     * Wait until the given callback returns true.
     *
     *
     * @throws \Illuminate\Process\Exceptions\ProcessTimedOutException
     */
    public function waitUntil(?callable $output = null): \Illuminate\Process\ProcessResult
    {
        try {
            $this->process->waitUntil($output);

            return new ProcessResult($this->process);
        } catch (SymfonyTimeoutException $e) {
            throw new ProcessTimedOutException($e, new ProcessResult($this->process));
        }
    }
}
