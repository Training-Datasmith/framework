<?php

declare (strict_types=1);
namespace Illuminate\Concurrency;

use Closure;
use Exception;
use Illuminate\Console\Application;
use Illuminate\Contracts\Concurrency\Driver;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Process\Pool;
use Illuminate\Support\Arr;
use function Illuminate\Support\defer;
use Illuminate\Support\Defer\Deferred_Callback;
use Laravel\Serializable_Closure\Serializable_Closure;
class Process_Driver implements Driver
{
    /**
     * Create a new process based concurrency driver.
     */
    public function __construct(protected Process_Factory $process_factory)
    {
    }
    /**
     * Run the given tasks concurrently and return an array containing the results.
     */
    public function run(Closure|array $tasks): array
    {
        $command = Application::format_command_string('invoke-serialized-closure');
        $results = $this->process_factory->pool(function (Pool $pool) use ($tasks, $command): void {
            foreach (Arr::wrap($tasks) as $key => $task) {
                $pool->as($key)->path(base_path())->env(['LARAVEL_INVOKABLE_CLOSURE' => base64_encode(serialize(new Serializable_Closure($task)))])->command($command);
            }
        })->start()->wait();
        return $results->collect()->map_with_keys(function (array $result, $key): array {
            if ($result->failed()) {
                throw new Exception('Concurrent process failed with exit code [' . $result->exit_code() . ']. Message: ' . $result->error_output());
            }
            $result = json_decode($result->output(), true);
            if (!$result['successful']) {
                throw new $result['exception'](...!empty(array_filter($result['parameters'])) ? $result['parameters'] : [$result['message']]);
            }
            return [$key => unserialize($result['result'])];
        })->all();
    }
    /**
     * Start the given tasks in the background after the current task has finished.
     */
    public function defer(Closure|array $tasks): Deferred_Callback
    {
        $command = Application::format_command_string('invoke-serialized-closure');
        return defer(function () use ($tasks, $command): void {
            foreach (Arr::wrap($tasks) as $task) {
                $this->process_factory->path(base_path())->env(['LARAVEL_INVOKABLE_CLOSURE' => base64_encode(serialize(new Serializable_Closure($task)))])->run($command . ' 2>&1 &');
            }
        });
    }
}