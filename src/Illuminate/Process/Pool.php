<?php

namespace Illuminate\Process;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @mixin \Illuminate\Process\Factory
 * @mixin \Illuminate\Process\PendingProcess
 */
class Pool
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
     * Create a new process pool.
     */
    public function __construct(/**
     * The process factory instance.
     */
    protected \Illuminate\Process\Factory $factory, callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Add a process to the pool with a key.
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
     * Start all of the processes in the pool.
     *
     *
     * @throws \InvalidArgumentException
     */
    public function start(?callable $output = null): \Illuminate\Process\InvokedProcessPool
    {
        call_user_func($this->callback, $this);

        return new InvokedProcessPool(
            (new Collection($this->pendingProcesses))
                ->each(function ($pendingProcess): void {
                    if (! $pendingProcess instanceof PendingProcess) {
                        throw new InvalidArgumentException('Process pool must only contain pending processes.');
                    }
                })
                ->mapWithKeys(fn($pendingProcess, $key) => [$key => $pendingProcess->start(output: $output ? function ($type, $buffer) use ($key, $output): void {
                    $output($type, $buffer, $key);
                } : null)])
                ->all()
        );
    }

    /**
     * Start and wait for the processes to finish.
     *
     * @return \Illuminate\Process\ProcessPoolResults
     */
    public function run()
    {
        return $this->wait();
    }

    /**
     * Start and wait for the processes to finish.
     *
     * @return \Illuminate\Process\ProcessPoolResults
     */
    public function wait()
    {
        return $this->start()->wait();
    }

    /**
     * Dynamically proxy methods calls to a new pending process.
     *
     * @param  array  $parameters
     * @return \Illuminate\Process\PendingProcess
     */
    public function __call(string $method, array $parameters)
    {
        return tap($this->factory->{$method}(...$parameters), function ($pendingProcess): void {
            $this->pendingProcesses[] = $pendingProcess;
        });
    }
}
