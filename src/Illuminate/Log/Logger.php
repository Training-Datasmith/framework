<?php

declare (strict_types=1);
namespace Illuminate\Log;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Log\Events\Message_Logged;
use Illuminate\Support\Traits\Conditionable;
use Psr\Log\Logger_Interface;
use RuntimeException;
class Logger implements Logger_Interface
{
    use Conditionable;
    /**
     * Any context to be added to logs.
     *
     * @var array
     */
    protected $context = [];
    /**
     * Create a new log writer instance.
     */
    public function __construct(
        /**
         * The underlying logger implementation.
         */
        protected \Psr\Log\Logger_Interface $logger,
        /**
         * The event dispatcher instance.
         */
        protected ?\Illuminate\Contracts\Events\Dispatcher $dispatcher = null
    )
    {
    }
    /**
     * Log an emergency message to the logs.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function emergency($message, array $context = []): void
    {
        $this->write_log(__FUNCTION__, $message, $context);
    }
    /**
     * Log an alert message to the logs.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function alert($message, array $context = []): void
    {
        $this->write_log(__FUNCTION__, $message, $context);
    }
    /**
     * Log a critical message to the logs.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function critical($message, array $context = []): void
    {
        $this->write_log(__FUNCTION__, $message, $context);
    }
    /**
     * Log an error message to the logs.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function error($message, array $context = []): void
    {
        $this->write_log(__FUNCTION__, $message, $context);
    }
    /**
     * Log a warning message to the logs.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function warning($message, array $context = []): void
    {
        $this->write_log(__FUNCTION__, $message, $context);
    }
    /**
     * Log a notice to the logs.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function notice($message, array $context = []): void
    {
        $this->write_log(__FUNCTION__, $message, $context);
    }
    /**
     * Log an informational message to the logs.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function info($message, array $context = []): void
    {
        $this->write_log(__FUNCTION__, $message, $context);
    }
    /**
     * Log a debug message to the logs.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function debug($message, array $context = []): void
    {
        $this->write_log(__FUNCTION__, $message, $context);
    }
    /**
     * Log a message to the logs.
     *
     * @param  string  $level
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function log($level, $message, array $context = []): void
    {
        $this->write_log($level, $message, $context);
    }
    /**
     * Dynamically pass log calls into the writer.
     *
     * @param  string  $level
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     */
    public function write($level, $message, array $context = []): void
    {
        $this->write_log($level, $message, $context);
    }
    /**
     * Write a message to the log.
     *
     * @param  string  $level
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     * @param  array  $context
     */
    protected function write_log($level, $message, $context): void
    {
        if (method_exists($this->logger, 'isHandling') && !$this->logger->is_handling($level)) {
            return;
        }
        $this->logger->{$level}($message = $this->format_message($message), $context = array_merge($this->context, $context));
        $this->fire_log_event($level, $message, $context);
    }
    /**
     * Add context to all future logs.
     *
     * @return $this
     */
    public function with_context(array $context = []): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
    /**
     * Flush the log context on all currently resolved channels.
     *
     * @param  string[]|null  $keys
     * @return $this
     */
    public function without_context(?array $keys = null): static
    {
        if (is_array($keys)) {
            $this->context = array_diff_key($this->context, array_flip($keys));
        } else {
            $this->context = [];
        }
        return $this;
    }
    /**
     * Register a new callback handler for when a log event is triggered.
     *
     *
     * @throws \RuntimeException
     */
    public function listen(Closure $callback): void
    {
        if (!isset($this->dispatcher)) {
            throw new RuntimeException('Events dispatcher has not been set.');
        }
        $this->dispatcher->listen(Message_Logged::class, $callback);
    }
    /**
     * Fires a log event.
     *
     * @param  string  $level
     * @param  string  $message
     * @return void
     */
    protected function fire_log_event($level, $message, array $context = [])
    {
        // Avoid dispatching the event multiple times if our logger instance is the LogManager...
        if ($this->logger instanceof Log_Manager && $this->logger->get_event_dispatcher() !== null) {
            return;
        }
        // If the event dispatcher is set, we will pass along the parameters to the
        // log listeners. These are useful for building profilers or other tools
        // that aggregate all of the log messages for a given "request" cycle.
        $this->dispatcher?->dispatch(new Message_Logged($level, $message, $context));
    }
    /**
     * Format the parameters for the logger.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|\Illuminate\Contracts\Support\Jsonable|\Illuminate\Support\Stringable|array|string  $message
     * @return string
     */
    protected function format_message($message)
    {
        return match (true) {
            is_array($message) => var_export($message, true),
            $message instanceof Jsonable => $message->to_json(),
            $message instanceof Arrayable => var_export($message->to_array(), true),
            default => (string) $message,
        };
    }
    /**
     * Get the underlying logger implementation.
     */
    public function get_logger(): \Psr\Log\Logger_Interface
    {
        return $this->logger;
    }
    /**
     * Get the event dispatcher instance.
     */
    public function get_event_dispatcher(): ?\Illuminate\Contracts\Events\Dispatcher
    {
        return $this->dispatcher;
    }
    /**
     * Set the event dispatcher instance.
     */
    public function set_event_dispatcher(Dispatcher $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }
    /**
     * Dynamically proxy method calls to the underlying logger.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->logger->{$method}(...$parameters);
    }
}