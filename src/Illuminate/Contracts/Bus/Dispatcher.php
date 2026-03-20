<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Bus;

interface Dispatcher
{
    /**
     * Dispatch a command to its appropriate handler.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatch($command);
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatch_sync($command, $handler = null);
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatch_now($command, $handler = null);
    /**
     * Determine if the given command has a handler.
     *
     * @param  mixed  $command
     * @return bool
     */
    public function has_command_handler($command);
    /**
     * Retrieve the handler for a command.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function get_command_handler($command);
    /**
     * Set the pipes commands should be piped through before dispatching.
     *
     * @return $this
     */
    public function pipe_through(array $pipes);
    /**
     * Map a command to a handler.
     *
     * @return $this
     */
    public function map(array $map);
}