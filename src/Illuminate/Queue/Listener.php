<?php

declare(strict_types=1);

namespace Illuminate\Queue;

use Closure;

use function Illuminate\Support\artisan_binary;

use function Illuminate\Support\php_binary;

use Symfony\Component\Process\Process;

class Listener
{
    /**
     * The environment the workers should run under.
     *
     * @var string
     */
    protected $environment;

    /**
     * The amount of seconds to wait before polling the queue.
     *
     * @var int
     */
    protected $sleep = 3;

    /**
     * The number of times to try a job before logging it failed.
     *
     * @var int
     */
    protected $maxTries = 0;

    /**
     * The output handler callback.
     *
     * @var \Closure|null
     */
    protected $outputHandler;

    /**
     * Create a new queue listener.
     *
     * @param  string  $commandPath
     */
    public function __construct(
        /**
         * The command working path.
         */
        protected $commandPath
    ) {
    }

    /**
     * Get the PHP binary.
     */
    protected function phpBinary(): string
    {
        return php_binary();
    }

    /**
     * Get the Artisan binary.
     */
    protected function artisanBinary(): string
    {
        return artisan_binary();
    }

    /**
     * Listen to the given queue connection.
     *
     * @param  string  $connection
     * @param  string  $queue
     */
    public function listen($connection, $queue, ListenerOptions $options): void
    {
        $process = $this->makeProcess($connection, $queue, $options);

        while (true) {
            $this->runProcess($process, $options->memory);

            if ($options->rest) {
                sleep($options->rest);
            }
        }
    }

    /**
     * Create a new Symfony process for the worker.
     *
     * @param  string  $connection
     * @param  string  $queue
     */
    public function makeProcess($connection, $queue, ListenerOptions $options): \Symfony\Component\Process\Process
    {
        $command = $this->createCommand(
            $connection,
            $queue,
            $options
        );

        // If the environment is set, we will append it to the command array so the
        // workers will run under the specified environment. Otherwise, they will
        // just run under the production environment which is not always right.
        if (isset($options->environment)) {
            $command = $this->addEnvironment($command, $options);
        }

        return new Process(
            $command,
            $this->commandPath,
            null,
            null,
            $options->timeout
        );
    }

    /**
     * Add the environment option to the given command.
     *
     * @param  array  $command
     */
    protected function addEnvironment($command, ListenerOptions $options): array
    {
        return array_merge($command, ["--env={$options->environment}"]);
    }

    /**
     * Create the command with the listener options.
     *
     * @param  string  $connection
     * @param  string  $queue
     */
    protected function createCommand($connection, $queue, ListenerOptions $options): array
    {
        return array_filter([
            $this->phpBinary(),
            $this->artisanBinary(),
            'queue:work',
            $connection,
            '--once',
            "--name={$options->name}",
            "--queue={$queue}",
            "--backoff={$options->backoff}",
            "--memory={$options->memory}",
            "--sleep={$options->sleep}",
            "--tries={$options->maxTries}",
            $options->force ? '--force' : null,
        ], fn (?string $value): bool => ! is_null($value));
    }

    /**
     * Run the given process.
     *
     * @param  int  $memory
     */
    public function runProcess(Process $process, $memory): void
    {
        $process->run(function ($type, $line): void {
            $this->handleWorkerOutput($type, $line);
        });

        // Once we have run the job we'll go check if the memory limit has been exceeded
        // for the script. If it has, we will kill this script so the process manager
        // will restart this with a clean slate of memory automatically on exiting.
        if ($this->memoryExceeded($memory)) {
            $this->stop();
        }
    }

    /**
     * Handle output from the worker process.
     *
     * @param  int  $type
     * @param  string  $line
     * @return void
     */
    protected function handleWorkerOutput($type, $line)
    {
        if (isset($this->outputHandler)) {
            call_user_func($this->outputHandler, $type, $line);
        }
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param  int  $memoryLimit
     */
    public function memoryExceeded($memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Stop listening and bail out of the script.
     */
    public function stop(): never
    {
        exit;
    }

    /**
     * Set the output handler callback.
     */
    public function setOutputHandler(Closure $outputHandler): void
    {
        $this->outputHandler = $outputHandler;
    }
}
