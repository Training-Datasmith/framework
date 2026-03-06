<?php

namespace Illuminate\Process;

use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Collection;

class FakeProcessResult implements ProcessResultContract
{
    /**
     * The process output.
     *
     * @var string
     */
    protected $output = '';

    /**
     * The process error output.
     *
     * @var string
     */
    protected $errorOutput = '';

    /**
     * Create a new process result instance.
     */
    public function __construct(/**
     * The command string.
     */
    protected string $command = '', /**
     * The process exit code.
     */
    protected int $exitCode = 0, array|string $output = '', array|string $errorOutput = '')
    {
        $this->output = $this->normalizeOutput($output);
        $this->errorOutput = $this->normalizeOutput($errorOutput);
    }

    /**
     * Normalize the given output into a string with newlines.
     *
     * @return string
     */
    protected function normalizeOutput(array|string $output)
    {
        if (empty($output)) {
            return '';
        }
        if (is_string($output)) {
            return rtrim($output, "\n")."\n";
        }
        if (is_array($output)) {
            return rtrim(
                (new Collection($output))
                    ->map(fn ($line): string => rtrim((string) $line, "\n")."\n")
                    ->implode(''),
                "\n"
            );
        }
    }

    /**
     * Get the original command executed by the process.
     *
     * @return string
     */
    public function command()
    {
        return $this->command;
    }

    /**
     * Create a new fake process result with the given command.
     */
    public function withCommand(string $command): \Illuminate\Process\FakeProcessResult
    {
        return new FakeProcessResult($command, $this->exitCode, $this->output, $this->errorOutput);
    }

    /**
     * Determine if the process was successful.
     */
    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Determine if the process failed.
     */
    public function failed(): bool
    {
        return ! $this->successful();
    }

    /**
     * Get the exit code of the process.
     *
     * @return int
     */
    public function exitCode()
    {
        return $this->exitCode;
    }

    /**
     * Get the standard output of the process.
     *
     * @return string
     */
    public function output()
    {
        return $this->output;
    }

    /**
     * Determine if the output contains the given string.
     */
    public function seeInOutput(string $output): bool
    {
        return str_contains($this->output(), $output);
    }

    /**
     * Get the error output of the process.
     *
     * @return string
     */
    public function errorOutput()
    {
        return $this->errorOutput;
    }

    /**
     * Determine if the error output contains the given string.
     */
    public function seeInErrorOutput(string $output): bool
    {
        return str_contains($this->errorOutput(), $output);
    }

    /**
     * Throw an exception if the process failed.
     *
     * @return $this
     * @throws \Illuminate\Process\Exceptions\ProcessFailedException
     */
    public function throw(?callable $callback = null): static
    {
        if ($this->successful()) {
            return $this;
        }

        $exception = new ProcessFailedException($this);

        if ($callback) {
            $callback($this, $exception);
        }

        throw $exception;
    }

    /**
     * Throw an exception if the process failed and the given condition is true.
     *
     * @return $this
     *
     * @throws \Throwable
     */
    public function throwIf(bool $condition, ?callable $callback = null)
    {
        if ($condition) {
            return $this->throw($callback);
        }

        return $this;
    }
}
