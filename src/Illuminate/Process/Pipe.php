<?php

declare(strict_types=1);

namespace Illuminate\Process;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @mixin \Illuminate\Process\Factory
 * @mixin \Illuminate\Process\PendingProcess
 */
class Pipe
{
    /**
     * The callback that resolves the pending processes.
     *
     * @var callable
     */
    protected $callback;

    /**
     * The array of pending processes.
     *
     * @var array
     */
    protected $pendingProcesses = [];

    /**
     * Create a new series of piped processes.
     */
    public function __construct(/**
     * The process factory instance.
     */
        protected \Illuminate\Process\Factory $factory,
        callable $callback
    ) {
        $this->callback = $callback;
    }

    /**
     * Add a process to the pipe with a key.
     *
     * @return \Illuminate\Process\PendingProcess
     */
    public function as(string $key)
    {
        return tap($this->factory->newPendingProcess(), function ($pendingProcess) use ($key): void {
            $this->pendingProcesses[$key] = $pendingProcess;
        });
    }

    /**
     * Runs the processes in the pipe.
     *
     * @return \Illuminate\Contracts\Process\ProcessResult
     * @throws \InvalidArgumentException
     */
    public function run(?callable $output = null)
    {
        call_user_func($this->callback, $this);

        return (new Collection($this->pendingProcesses))
            ->reduce(function ($previousProcessResult, $pendingProcess, $key) use ($output) {
                if (! $pendingProcess instanceof PendingProcess) {
                    throw new InvalidArgumentException('Process pipe must only contain pending processes.');
                }

                if ($previousProcessResult && $previousProcessResult->failed()) {
                    return $previousProcessResult;
                }

                return $pendingProcess->when(
                    $previousProcessResult,
                    fn (): \Illuminate\Process\PendingProcess => $pendingProcess->input($previousProcessResult->output())
                )->run(output: $output ? function ($type, $buffer) use ($key, $output): void {
                    $output($type, $buffer, $key);
                } : null);
            });
    }

    /**
     * Dynamically proxy methods calls to a new pending process.
     *
     * @return \Illuminate\Process\PendingProcess
     */
    public function __call(string $method, array $parameters)
    {
        return tap($this->factory->{$method}(...$parameters), function ($pendingProcess): void {
            $this->pendingProcesses[] = $pendingProcess;
        });
    }
}
