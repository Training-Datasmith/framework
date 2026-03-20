<?php

declare (strict_types=1);
namespace Illuminate\Console;

/**
 * @internal
 */
class Signals
{
    /**
     * The signal registry's previous list of handlers.
     *
     * @var array<int, array<int, callable(int): void>>|null
     */
    protected $previous_handlers;
    /**
     * The current availability resolver, if any.
     *
     * @var (callable(): bool)|null
     */
    protected static $availability_resolver;
    /**
     * Create a new signal registrar instance.
     *
     * @param  \Symfony\Component\Console\SignalRegistry\SignalRegistry  $registry
     */
    public function __construct(
        /**
         * The signal registry instance.
         */
        protected $registry
    )
    {
        $this->previous_handlers = $this->get_handlers();
    }
    /**
     * Register a new signal handler.
     *
     * @param  int  $signal
     * @param  callable(int $signal): void  $callback
     */
    public function register($signal, callable $callback): void
    {
        $this->previous_handlers[$signal] ??= $this->initialize_signal($signal);
        $handlers = $this->get_handlers();
        $handlers[$signal] ??= $this->initialize_signal($signal);
        $this->set_handlers($handlers);
        $this->registry->register($signal, $callback);
        $handlers = $this->get_handlers();
        $last_handler_inserted = array_pop($handlers[$signal]);
        array_unshift($handlers[$signal], $last_handler_inserted);
        $this->set_handlers($handlers);
    }
    /**
     * Gets the signal's existing handler in array format.
     *
     * @return array<int, callable(int $signal): void>|null
     */
    protected function initialize_signal($signal): ?array
    {
        return is_callable($existing_handler = pcntl_signal_get_handler($signal)) ? [$existing_handler] : null;
    }
    /**
     * Unregister the current signal handlers.
     */
    public function unregister(): void
    {
        $previous_handlers = $this->previous_handlers;
        foreach ($previous_handlers as $signal => $handler) {
            if (is_null($handler)) {
                pcntl_signal($signal, SIG_DFL);
                unset($previous_handlers[$signal]);
            }
        }
        $this->set_handlers($previous_handlers);
    }
    /**
     * Execute the given callback if "signals" should be used and are available.
     *
     * @param  callable  $callback
     */
    public static function when_available($callback): void
    {
        $resolver = static::$availability_resolver;
        if ($resolver()) {
            $callback();
        }
    }
    /**
     * Get the registry's handlers.
     *
     * @return array<int, array<int, callable>>
     */
    protected function get_handlers(): mixed
    {
        return (fn() => $this->signal_handlers)->call($this->registry);
    }
    /**
     * Set the registry's handlers.
     *
     * @param  array<int, array<int, callable(int $signal):void>>  $handlers
     * @return void
     */
    protected function set_handlers($handlers)
    {
        (fn() => $this->signal_handlers = $handlers)->call($this->registry);
    }
    /**
     * Set the availability resolver.
     *
     * @param  (callable(): bool)  $resolver
     */
    public static function resolve_availability_using($resolver): void
    {
        static::$availability_resolver = $resolver;
    }
}