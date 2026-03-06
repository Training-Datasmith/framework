<?php

declare(strict_types=1);

namespace Illuminate\Support\Testing\Fakes;

use Closure;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\ChainedBatch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ReflectsClosures;
use PHPUnit\Framework\Assert as PHPUnit;
use RuntimeException;

class BusFake implements Fake, QueueingDispatcher
{
    use ReflectsClosures;

    /**
     * The original Bus dispatcher implementation.
     *
     * @var \Illuminate\Contracts\Bus\QueueingDispatcher
     */
    public $dispatcher;

    /**
     * The job types that should be intercepted instead of dispatched.
     */
    protected array $jobsToFake;

    /**
     * The job types that should be dispatched instead of faked.
     *
     * @var array
     */
    protected $jobsToDispatch = [];

    /**
     * The fake repository to track batched jobs.
     */
    protected \Illuminate\Bus\BatchRepository $batchRepository;

    /**
     * The commands that have been dispatched.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * The commands that have been dispatched synchronously.
     *
     * @var array
     */
    protected $commandsSync = [];

    /**
     * The commands that have been dispatched after the response has been sent.
     *
     * @var array
     */
    protected $commandsAfterResponse = [];

    /**
     * The batches that have been dispatched.
     *
     * @var array
     */
    protected $batches = [];

    /**
     * Indicates if commands should be serialized and restored when pushed to the Bus.
     */
    protected bool $serializeAndRestore = false;

    /**
     * Create a new bus fake instance.
     *
     * @param  array|string  $jobsToFake
     */
    public function __construct(QueueingDispatcher $dispatcher, $jobsToFake = [], ?BatchRepository $batchRepository = null)
    {
        $this->dispatcher = $dispatcher;
        $this->jobsToFake = Arr::wrap($jobsToFake);
        $this->batchRepository = $batchRepository ?: new BatchRepositoryFake();
    }

    /**
     * Specify the jobs that should be dispatched instead of faked.
     *
     * @param  array|string  $jobsToDispatch
     * @return $this
     */
    public function except($jobsToDispatch): static
    {
        $this->jobsToDispatch = array_merge($this->jobsToDispatch, Arr::wrap($jobsToDispatch));

        return $this;
    }

    /**
     * Assert if a job was dispatched based on a truth-test callback.
     *
     * @param  string|\Closure  $command
     * @param  callable|int|null  $callback
     * @return void
     */
    public function assertDispatched($command, $callback = null)
    {
        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        if (is_numeric($callback)) {
            return $this->assertDispatchedTimes($command, $callback);
        }

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->count() > 0 ||
            $this->dispatchedAfterResponse($command, $callback)->count() > 0 ||
            $this->dispatchedSync($command, $callback)->count() > 0,
            "The expected [{$command}] job was not dispatched."
        );
    }

    /**
     * Assert if a job was pushed exactly once.
     *
     * @param  string|\Closure  $command
     */
    public function assertDispatchedOnce($command): void
    {
        $this->assertDispatchedTimes($command, 1);
    }

    /**
     * Assert if a job was pushed a number of times.
     *
     * @param  string|\Closure  $command
     * @param  int  $times
     */
    public function assertDispatchedTimes($command, $times = 1): void
    {
        $callback = null;

        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        $count = $this->dispatched($command, $callback)->count() +
                 $this->dispatchedAfterResponse($command, $callback)->count() +
                 $this->dispatchedSync($command, $callback)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            sprintf(
                "The expected [{$command}] job was pushed {$count} %s instead of {$times} %s.",
                Str::plural('time', $count),
                Str::plural('time', $times)
            )
        );
    }

    /**
     * Determine if a job was dispatched based on a truth-test callback.
     *
     * @param  string|\Closure  $command
     * @param  callable|null  $callback
     */
    public function assertNotDispatched($command, $callback = null): void
    {
        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->count() === 0 &&
            $this->dispatchedAfterResponse($command, $callback)->count() === 0 &&
            $this->dispatchedSync($command, $callback)->count() === 0,
            "The unexpected [{$command}] job was dispatched."
        );
    }

    /**
     * Assert that no jobs were dispatched.
     */
    public function assertNothingDispatched(): void
    {
        $commandNames = implode("\n- ", array_keys($this->commands));

        PHPUnit::assertEmpty($this->commands, "The following jobs were dispatched unexpectedly:\n\n- $commandNames\n");
    }

    /**
     * Assert if a job was explicitly dispatched synchronously based on a truth-test callback.
     *
     * @param  string|\Closure  $command
     * @param  callable|int|null  $callback
     * @return void
     */
    public function assertDispatchedSync($command, $callback = null)
    {
        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        if (is_numeric($callback)) {
            return $this->assertDispatchedSyncTimes($command, $callback);
        }

        PHPUnit::assertTrue(
            $this->dispatchedSync($command, $callback)->count() > 0,
            "The expected [{$command}] job was not dispatched synchronously."
        );
    }

    /**
     * Assert if a job was pushed synchronously a number of times.
     *
     * @param  string|\Closure  $command
     * @param  int  $times
     */
    public function assertDispatchedSyncTimes($command, $times = 1): void
    {
        $callback = null;

        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        $count = $this->dispatchedSync($command, $callback)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            sprintf(
                "The expected [{$command}] job was synchronously pushed {$count} %s instead of {$times} %s.",
                Str::plural('time', $count),
                Str::plural('time', $times)
            )
        );
    }

    /**
     * Determine if a job was dispatched based on a truth-test callback.
     *
     * @param  string|\Closure  $command
     * @param  callable|null  $callback
     */
    public function assertNotDispatchedSync($command, $callback = null): void
    {
        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        PHPUnit::assertCount(
            0,
            $this->dispatchedSync($command, $callback),
            "The unexpected [{$command}] job was dispatched synchronously."
        );
    }

    /**
     * Assert if a job was dispatched after the response was sent based on a truth-test callback.
     *
     * @param  string|\Closure  $command
     * @param  callable|int|null  $callback
     * @return void
     */
    public function assertDispatchedAfterResponse($command, $callback = null)
    {
        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        if (is_numeric($callback)) {
            return $this->assertDispatchedAfterResponseTimes($command, $callback);
        }

        PHPUnit::assertTrue(
            $this->dispatchedAfterResponse($command, $callback)->count() > 0,
            "The expected [{$command}] job was not dispatched after sending the response."
        );
    }

    /**
     * Assert if a job was pushed after the response was sent a number of times.
     *
     * @param  string|\Closure  $command
     * @param  int  $times
     */
    public function assertDispatchedAfterResponseTimes($command, $times = 1): void
    {
        $callback = null;

        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        $count = $this->dispatchedAfterResponse($command, $callback)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            sprintf(
                "The expected [{$command}] job was pushed {$count} %s instead of {$times} %s.",
                Str::plural('time', $count),
                Str::plural('time', $times)
            )
        );
    }

    /**
     * Determine if a job was dispatched based on a truth-test callback.
     *
     * @param  string|\Closure  $command
     * @param  callable|null  $callback
     */
    public function assertNotDispatchedAfterResponse($command, $callback = null): void
    {
        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        PHPUnit::assertCount(
            0,
            $this->dispatchedAfterResponse($command, $callback),
            "The unexpected [{$command}] job was dispatched after sending the response."
        );
    }

    /**
     * Assert if a chain of jobs was dispatched.
     */
    public function assertChained(array $expectedChain): void
    {
        $command = $expectedChain[0];

        $expectedChain = array_slice($expectedChain, 1);

        $callback = null;

        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        } elseif ($command instanceof ChainedBatchTruthTest) {
            $instance = $command;

            $command = ChainedBatch::class;

            $callback = fn ($job): bool => $instance($job->toPendingBatch());
        } elseif (! is_string($command)) {
            $instance = $command;

            $command = $instance::class;

            $callback = (fn ($job): bool => serialize($this->resetChainPropertiesToDefaults($job)) === serialize($instance));
        }

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->isNotEmpty(),
            "The expected [{$command}] job was not dispatched."
        );

        $this->assertDispatchedWithChainOfObjects($command, $expectedChain, $callback);
    }

    /**
     * Assert no chained jobs was dispatched.
     */
    public function assertNothingChained(): void
    {
        $this->assertNothingDispatched();
    }

    /**
     * Reset the chain properties to their default values on the job.
     *
     * @param  mixed  $job
     * @return mixed
     */
    protected function resetChainPropertiesToDefaults($job)
    {
        return tap(clone $job, function ($job): void {
            $job->chainConnection = null;
            $job->chainQueue = null;
            $job->chainCatchCallbacks = null;
            $job->chained = [];
        });
    }

    /**
     * Assert if a job was dispatched with an empty chain based on a truth-test callback.
     *
     * @param  string|\Closure  $command
     * @param  callable|null  $callback
     */
    public function assertDispatchedWithoutChain($command, $callback = null): void
    {
        if ($command instanceof Closure) {
            [$command, $callback] = [$this->firstClosureParameterType($command), $command];
        }

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->isNotEmpty(),
            "The expected [{$command}] job was not dispatched."
        );

        $this->assertDispatchedWithChainOfObjects($command, [], $callback);
    }

    /**
     * Assert if a job was dispatched with chained jobs based on a truth-test callback.
     *
     * @param  string  $command
     * @param  array  $expectedChain
     * @param  callable|null  $callback
     * @return void
     */
    protected function assertDispatchedWithChainOfObjects($command, $expectedChain, $callback)
    {
        $chain = $expectedChain;

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->filter(function ($job) use ($chain): bool {
                if (count($chain) !== count($job->chained)) {
                    return false;
                }

                foreach ($job->chained as $index => $serializedChainedJob) {
                    if ($chain[$index] instanceof ChainedBatchTruthTest) {
                        $chainedBatch = unserialize($serializedChainedJob);

                        if (! $chainedBatch instanceof ChainedBatch ||
                            ! $chain[$index]($chainedBatch->toPendingBatch())) {
                            return false;
                        }
                    } elseif ($chain[$index] instanceof Closure) {
                        [$expectedType, $callback] = [$this->firstClosureParameterType($chain[$index]), $chain[$index]];

                        $chainedJob = unserialize($serializedChainedJob);

                        if (! $chainedJob instanceof $expectedType) {
                            throw new RuntimeException('The chained job was expected to be of type '.$expectedType.', '.$chainedJob::class.' chained.');
                        }

                        if (! $callback($chainedJob)) {
                            return false;
                        }
                    } elseif (is_string($chain[$index])) {
                        if ($chain[$index] != unserialize($serializedChainedJob)::class) {
                            return false;
                        }
                    } elseif (serialize($chain[$index]) != $serializedChainedJob) {
                        return false;
                    }
                }

                return true;
            })->isNotEmpty(),
            'The expected chain was not dispatched.'
        );
    }

    /**
     * Create a new assertion about a chained batch.
     *
     * @param  \Closure(\Illuminate\Bus\PendingBatch): bool  $callback
     */
    public function chainedBatch(Closure $callback): \Illuminate\Support\Testing\Fakes\ChainedBatchTruthTest
    {
        return new ChainedBatchTruthTest($callback);
    }

    /**
     * Assert if a batch was dispatched based on a truth-test callback.
     *
     * @param  array|callable(\Illuminate\Bus\PendingBatch): bool  $callback
     */
    public function assertBatched(callable|array $callback): void
    {
        $callback = is_array($callback) ? fn (PendingBatchFake $batch): bool => $batch->hasJobs($callback) : $callback;

        PHPUnit::assertTrue(
            $this->batched($callback)->count() > 0,
            'The expected batch was not dispatched.'
        );
    }

    /**
     * Assert the number of batches that have been dispatched.
     *
     * @param  int  $count
     */
    public function assertBatchCount($count): void
    {
        PHPUnit::assertCount(
            $count,
            $this->batches,
        );
    }

    /**
     * Assert that no batched jobs were dispatched.
     */
    public function assertNothingBatched(): void
    {
        $jobNames = (new Collection($this->batches))
            ->map(fn ($batch) => $batch->jobs->map(fn ($job): string|false => $job::class))
            ->flatten()
            ->join("\n- ");

        PHPUnit::assertEmpty($this->batches, "The following batched jobs were dispatched unexpectedly:\n\n- $jobNames\n");
    }

    /**
     * Assert that no jobs were dispatched, chained, or batched.
     */
    public function assertNothingPlaced(): void
    {
        $this->assertNothingDispatched();
        $this->assertNothingBatched();
    }

    /**
     * Get all of the jobs matching a truth-test callback.
     *
     * @param  string  $command
     * @param  callable|null  $callback
     */
    public function dispatched($command, $callback = null): \Illuminate\Support\Collection
    {
        if (! $this->hasDispatched($command)) {
            return new Collection();
        }

        $callback = $callback ?: fn (): true => true;

        return (new Collection($this->commands[$command]))->filter(fn ($command) => $callback($command));
    }

    /**
     * Get all of the jobs dispatched synchronously matching a truth-test callback.
     *
     * @param  callable|null  $callback
     */
    public function dispatchedSync(string $command, $callback = null): \Illuminate\Support\Collection
    {
        if (! $this->hasDispatchedSync($command)) {
            return new Collection();
        }

        $callback = $callback ?: fn (): true => true;

        return (new Collection($this->commandsSync[$command]))->filter(fn ($command) => $callback($command));
    }

    /**
     * Get all of the jobs dispatched after the response was sent matching a truth-test callback.
     *
     * @param  callable|null  $callback
     */
    public function dispatchedAfterResponse(string $command, $callback = null): \Illuminate\Support\Collection
    {
        if (! $this->hasDispatchedAfterResponse($command)) {
            return new Collection();
        }

        $callback = $callback ?: fn (): true => true;

        return (new Collection($this->commandsAfterResponse[$command]))->filter(fn ($command) => $callback($command));
    }

    /**
     * Get all of the pending batches matching a truth-test callback.
     *
     * @param  callable(\Illuminate\Bus\PendingBatch): bool  $callback
     * @return \Illuminate\Support\Collection<int, \Illuminate\Bus\PendingBatch>
     */
    public function batched(callable $callback): \Illuminate\Support\Collection
    {
        if (empty($this->batches)) {
            return new Collection();
        }

        return (new Collection($this->batches))->filter(fn ($batch) => $callback($batch));
    }

    /**
     * Determine if there are any stored commands for a given class.
     *
     * @param  string  $command
     */
    public function hasDispatched($command): bool
    {
        return isset($this->commands[$command]) && ! empty($this->commands[$command]);
    }

    /**
     * Determine if there are any stored commands for a given class.
     *
     * @param  string  $command
     */
    public function hasDispatchedSync($command): bool
    {
        return isset($this->commandsSync[$command]) && ! empty($this->commandsSync[$command]);
    }

    /**
     * Determine if there are any stored commands for a given class.
     *
     * @param  string  $command
     */
    public function hasDispatchedAfterResponse($command): bool
    {
        return isset($this->commandsAfterResponse[$command]) && ! empty($this->commandsAfterResponse[$command]);
    }

    /**
     * Dispatch a command to its appropriate handler.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatch($command)
    {
        if ($this->shouldFakeJob($command)) {
            $this->commands[$command::class][] = $this->getCommandRepresentation($command);
        } else {
            return $this->dispatcher->dispatch($command);
        }
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatchSync($command, $handler = null)
    {
        if ($this->shouldFakeJob($command)) {
            $this->commandsSync[$command::class][] = $this->getCommandRepresentation($command);
        } else {
            return $this->dispatcher->dispatchSync($command, $handler);
        }
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatchNow($command, $handler = null)
    {
        if ($this->shouldFakeJob($command)) {
            $this->commands[$command::class][] = $this->getCommandRepresentation($command);
        } else {
            return $this->dispatcher->dispatchNow($command, $handler);
        }
    }

    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatchToQueue($command)
    {
        if ($this->shouldFakeJob($command)) {
            $this->commands[$command::class][] = $this->getCommandRepresentation($command);
        } else {
            return $this->dispatcher->dispatchToQueue($command);
        }
    }

    /**
     * Dispatch a command to its appropriate handler.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatchAfterResponse($command)
    {
        if ($this->shouldFakeJob($command)) {
            $this->commandsAfterResponse[$command::class][] = $this->getCommandRepresentation($command);
        } else {
            return $this->dispatcher->dispatch($command);
        }
    }

    /**
     * Create a new chain of queueable jobs.
     *
     * @param  \Illuminate\Support\Collection|array|null  $jobs
     * @return \Illuminate\Foundation\Bus\PendingChain
     */
    public function chain($jobs = null): \Illuminate\Support\Testing\Fakes\PendingChainFake
    {
        $jobs = Collection::wrap($jobs);
        $jobs = ChainedBatch::prepareNestedBatches($jobs);

        return new PendingChainFake($this, $jobs->shift(), $jobs->toArray());
    }

    /**
     * Attempt to find the batch with the given ID.
     *
     * @return \Illuminate\Bus\Batch|null
     */
    public function findBatch(string $batchId)
    {
        return $this->batchRepository->find($batchId);
    }

    /**
     * Create a new batch of queueable jobs.
     *
     * @param  \Illuminate\Support\Collection|array  $jobs
     * @return \Illuminate\Bus\PendingBatch
     */
    public function batch($jobs): \Illuminate\Support\Testing\Fakes\PendingBatchFake
    {
        return new PendingBatchFake($this, Collection::wrap($jobs));
    }

    /**
     * Dispatch an empty job batch for testing.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function dispatchFakeBatch(string $name = '')
    {
        return $this->batch([])->name($name)->dispatch();
    }

    /**
     * Record the fake pending batch dispatch.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function recordPendingBatch(PendingBatch $pendingBatch)
    {
        $this->batches[] = $pendingBatch;

        return $this->batchRepository->store($pendingBatch);
    }

    /**
     * Determine if a command should be faked or actually dispatched.
     *
     * @param  mixed  $command
     * @return bool
     */
    protected function shouldFakeJob($command)
    {
        if ($this->shouldDispatchCommand($command)) {
            return false;
        }

        if (empty($this->jobsToFake)) {
            return true;
        }

        return (new Collection($this->jobsToFake))
            ->filter(fn ($job) => $job instanceof Closure
                ? $job($command)
                : $job === $command::class)->isNotEmpty();
    }

    /**
     * Determine if a command should be dispatched or not.
     *
     * @param  mixed  $command
     */
    protected function shouldDispatchCommand($command): bool
    {
        return (new Collection($this->jobsToDispatch))
            ->filter(fn ($job) => $job instanceof Closure
                ? $job($command)
                : $job === $command::class)->isNotEmpty();
    }

    /**
     * Specify if commands should be serialized and restored when being batched.
     *
     * @return $this
     */
    public function serializeAndRestore(bool $serializeAndRestore = true): static
    {
        $this->serializeAndRestore = $serializeAndRestore;

        return $this;
    }

    /**
     * Serialize and unserialize the command to simulate the queueing process.
     *
     * @param  mixed  $command
     */
    protected function serializeAndRestoreCommand($command): mixed
    {
        return unserialize(serialize($command));
    }

    /**
     * Return the command representation that should be stored.
     *
     * @param  mixed  $command
     * @return mixed
     */
    protected function getCommandRepresentation($command)
    {
        return $this->serializeAndRestore ? $this->serializeAndRestoreCommand($command) : $command;
    }

    /**
     * Set the pipes commands should be piped through before dispatching.
     *
     * @return $this
     */
    public function pipeThrough(array $pipes): static
    {
        $this->dispatcher->pipeThrough($pipes);

        return $this;
    }

    /**
     * Determine if the given command has a handler.
     *
     * @param  mixed  $command
     * @return bool
     */
    public function hasCommandHandler($command)
    {
        return $this->dispatcher->hasCommandHandler($command);
    }

    /**
     * Retrieve the handler for a command.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function getCommandHandler($command)
    {
        return $this->dispatcher->getCommandHandler($command);
    }

    /**
     * Map a command to a handler.
     *
     * @return $this
     */
    public function map(array $map): static
    {
        $this->dispatcher->map($map);

        return $this;
    }

    /**
     * Get the batches that have been dispatched.
     *
     * @return array
     */
    public function dispatchedBatches()
    {
        return $this->batches;
    }
}
