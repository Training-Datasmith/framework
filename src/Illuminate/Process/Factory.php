<?php

declare(strict_types=1);

namespace Illuminate\Process;

use Closure;
use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use PHPUnit\Framework\Assert as PHPUnit;

class Factory
{
    use Macroable {
        __call as macroCall;
    }

    /**
     * Indicates if the process factory has faked process handlers.
     *
     * @var bool
     */
    protected $recording = false;

    /**
     * All of the recorded processes.
     *
     * @var array
     */
    protected $recorded = [];

    /**
     * The registered fake handler callbacks.
     *
     * @var array
     */
    protected $fakeHandlers = [];

    /**
     * Indicates that an exception should be thrown if any process is not faked.
     *
     * @var bool
     */
    protected $preventStrayProcesses = false;

    /**
     * Create a new fake process response for testing purposes.
     */
    public function result(array|string $output = '', array|string $errorOutput = '', int $exitCode = 0): \Illuminate\Process\FakeProcessResult
    {
        return new FakeProcessResult(
            output: $output,
            errorOutput: $errorOutput,
            exitCode: $exitCode,
        );
    }

    /**
     * Begin describing a fake process lifecycle.
     */
    public function describe(): \Illuminate\Process\FakeProcessDescription
    {
        return new FakeProcessDescription();
    }

    /**
     * Begin describing a fake process sequence.
     */
    public function sequence(array $processes = []): \Illuminate\Process\FakeProcessSequence
    {
        return new FakeProcessSequence($processes);
    }

    /**
     * Indicate that the process factory should fake processes.
     *
     * @return $this
     */
    public function fake(Closure|array|null $callback = null): static
    {
        $this->recording = true;

        if (is_null($callback)) {
            $this->fakeHandlers = ['*' => fn (): \Illuminate\Process\FakeProcessResult => new FakeProcessResult()];

            return $this;
        }

        if ($callback instanceof Closure) {
            $this->fakeHandlers = ['*' => $callback];

            return $this;
        }

        foreach ($callback as $command => $handler) {
            $this->fakeHandlers[is_numeric($command) ? '*' : $command] = $handler instanceof Closure
                ? $handler
                : fn () => $handler;
        }

        return $this;
    }

    /**
     * Determine if the process factory has fake process handlers and is recording processes.
     *
     * @return bool
     */
    public function isRecording()
    {
        return $this->recording;
    }

    /**
     * Record the given process if processes should be recorded.
     *
     * @return $this
     */
    public function recordIfRecording(PendingProcess $process, ProcessResultContract $result): static
    {
        if ($this->isRecording()) {
            $this->record($process, $result);
        }

        return $this;
    }

    /**
     * Record the given process.
     *
     * @return $this
     */
    public function record(PendingProcess $process, ProcessResultContract $result): static
    {
        $this->recorded[] = [$process, $result];

        return $this;
    }

    /**
     * Indicate that an exception should be thrown if any process is not faked.
     *
     * @return $this
     */
    public function preventStrayProcesses(bool $prevent = true): static
    {
        $this->preventStrayProcesses = $prevent;

        return $this;
    }

    /**
     * Determine if stray processes are being prevented.
     *
     * @return bool
     */
    public function preventingStrayProcesses()
    {
        return $this->preventStrayProcesses;
    }

    /**
     * Assert that a process was recorded matching a given truth test.
     *
     * @return $this
     */
    public function assertRan(Closure|string $callback): static
    {
        $callback = is_string($callback) ? fn ($process): bool => $process->command === $callback : $callback;

        PHPUnit::assertTrue(
            (new Collection($this->recorded))->filter(fn ($pair) => $callback($pair[0], $pair[1]))->count() > 0,
            'An expected process was not invoked.'
        );

        return $this;
    }

    /**
     * Assert that a process was recorded a given number of times matching a given truth test.
     *
     * @return $this
     */
    public function assertRanTimes(Closure|string $callback, int $times = 1): static
    {
        $callback = is_string($callback) ? fn ($process): bool => $process->command === $callback : $callback;

        $count = (new Collection($this->recorded))
            ->filter(fn ($pair) => $callback($pair[0], $pair[1]))
            ->count();

        PHPUnit::assertSame(
            $times,
            $count,
            "An expected process ran {$count} times instead of {$times} times."
        );

        return $this;
    }

    /**
     * Assert that a process was not recorded matching a given truth test.
     *
     * @return $this
     */
    public function assertNotRan(Closure|string $callback): static
    {
        $callback = is_string($callback) ? fn ($process): bool => $process->command === $callback : $callback;

        PHPUnit::assertTrue(
            (new Collection($this->recorded))->filter(fn ($pair) => $callback($pair[0], $pair[1]))->count() === 0,
            'An unexpected process was invoked.'
        );

        return $this;
    }

    /**
     * Assert that a process was not recorded matching a given truth test.
     *
     * @return $this
     */
    public function assertDidntRun(Closure|string $callback): static
    {
        return $this->assertNotRan($callback);
    }

    /**
     * Assert that no processes were recorded.
     *
     * @return $this
     */
    public function assertNothingRan(): static
    {
        PHPUnit::assertEmpty(
            $this->recorded,
            'An unexpected process was invoked.'
        );

        return $this;
    }

    /**
     * Start defining a pool of processes.
     */
    public function pool(callable $callback): \Illuminate\Process\Pool
    {
        return new Pool($this, $callback);
    }

    /**
     * Start defining a series of piped processes.
     *
     * @return \Illuminate\Contracts\Process\ProcessResult
     */
    public function pipe(callable|array $callback, ?callable $output = null)
    {
        return is_array($callback)
            ? (new Pipe($this, fn ($pipe) => (new Collection($callback))->each(
                fn ($command) => $pipe->command($command)
            )))->run(output: $output)
            : (new Pipe($this, $callback))->run(output: $output);
    }

    /**
     * Run a pool of processes and wait for them to finish executing.
     */
    public function concurrently(callable $callback, ?callable $output = null): \Illuminate\Process\ProcessPoolResults
    {
        return (new Pool($this, $callback))->start($output)->wait();
    }

    /**
     * Create a new pending process associated with this factory.
     */
    public function newPendingProcess(): \Illuminate\Process\PendingProcess
    {
        return (new PendingProcess($this))->withFakeHandlers($this->fakeHandlers);
    }

    /**
     * Dynamically proxy methods to a new pending process instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return $this->newPendingProcess()->{$method}(...$parameters);
    }
}
