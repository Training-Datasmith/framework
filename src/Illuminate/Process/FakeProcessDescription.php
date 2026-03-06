<?php

namespace Illuminate\Process;

use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class FakeProcessDescription
{
    /**
     * The process' ID.
     *
     * @var int|null
     */
    public $processId = 1000;

    /**
     * All of the process' output in the order it was described.
     *
     * @var array
     */
    public $output = [];

    /**
     * The process' exit code.
     *
     * @var int
     */
    public $exitCode = 0;

    /**
     * The number of times the process should indicate that it is "running".
     *
     * @var int
     */
    public $runIterations = 0;

    /**
     * Specify the process ID that should be assigned to the process.
     *
     * @return $this
     */
    public function id(int $processId): static
    {
        $this->processId = $processId;

        return $this;
    }

    /**
     * Describe a line of standard output.
     *
     * @return $this
     */
    public function output(array|string $output): static
    {
        if (is_array($output)) {
            (new Collection($output))->each(fn (array|string $line) => $this->output($line));

            return $this;
        }

        $this->output[] = ['type' => 'out', 'buffer' => rtrim($output, "\n")."\n"];

        return $this;
    }

    /**
     * Describe a line of error output.
     *
     * @return $this
     */
    public function errorOutput(array|string $output): static
    {
        if (is_array($output)) {
            (new Collection($output))->each(fn (array|string $line) => $this->errorOutput($line));

            return $this;
        }

        $this->output[] = ['type' => 'err', 'buffer' => rtrim($output, "\n")."\n"];

        return $this;
    }

    /**
     * Replace the entire output buffer with the given string.
     *
     * @return $this
     */
    public function replaceOutput(string $output): static
    {
        $this->output = (new Collection($this->output))
            ->reject(fn ($output): bool => $output['type'] === 'out')
            ->values()
            ->all();

        if (strlen($output) > 0) {
            $this->output[] = [
                'type' => 'out',
                'buffer' => rtrim($output, "\n")."\n",
            ];
        }

        return $this;
    }

    /**
     * Replace the entire error output buffer with the given string.
     *
     * @return $this
     */
    public function replaceErrorOutput(string $output): static
    {
        $this->output = (new Collection($this->output))
            ->reject(fn ($output): bool => $output['type'] === 'err')
            ->values()
            ->all();

        if (strlen($output) > 0) {
            $this->output[] = [
                'type' => 'err',
                'buffer' => rtrim($output, "\n")."\n",
            ];
        }

        return $this;
    }

    /**
     * Specify the process exit code.
     *
     * @return $this
     */
    public function exitCode(int $exitCode): static
    {
        $this->exitCode = $exitCode;

        return $this;
    }

    /**
     * Specify how many times the "isRunning" method should return "true".
     *
     * @return $this
     */
    public function iterations(int $iterations)
    {
        return $this->runsFor(iterations: $iterations);
    }

    /**
     * Specify how many times the "isRunning" method should return "true".
     *
     * @return $this
     */
    public function runsFor(int $iterations): static
    {
        $this->runIterations = $iterations;

        return $this;
    }

    /**
     * Turn the fake process description into an actual process.
     */
    public function toSymfonyProcess(string $command): \Symfony\Component\Process\Process
    {
        return Process::fromShellCommandline($command);
    }

    /**
     * Convert the process description into a process result.
     *
     * @return \Illuminate\Contracts\Process\ProcessResult
     */
    public function toProcessResult(string $command): \Illuminate\Process\FakeProcessResult
    {
        return new FakeProcessResult(
            command: $command,
            exitCode: $this->exitCode,
            output: $this->resolveOutput(),
            errorOutput: $this->resolveErrorOutput(),
        );
    }

    /**
     * Resolve the standard output as a string.
     */
    protected function resolveOutput(): string
    {
        $output = (new Collection($this->output))
            ->filter(fn ($output): bool => $output['type'] === 'out');

        return $output->isNotEmpty()
            ? rtrim((string) $output->map->buffer->implode(''), "\n")."\n"
            : '';
    }

    /**
     * Resolve the error output as a string.
     */
    protected function resolveErrorOutput(): string
    {
        $output = (new Collection($this->output))
            ->filter(fn ($output): bool => $output['type'] === 'err');

        return $output->isNotEmpty()
            ? rtrim((string) $output->map->buffer->implode(''), "\n")."\n"
            : '';
    }
}
