<?php

declare (strict_types=1);
namespace Illuminate\Console\Concerns;

use Illuminate\Console\Signals;
use Illuminate\Support\Collection;
trait Interacts_With_Signals
{
    /**
     * The signal registrar instance.
     *
     * @var \Illuminate\Console\Signals|null
     */
    protected $signals;
    /**
     * Define a callback to be run when the given signal(s) occurs.
     *
     * @template TSignals of iterable<array-key, int>|int
     *
     * @param  (\Closure():(TSignals))|TSignals  $signals
     * @param  callable(int $signal): void  $callback
     */
    public function trap($signals, $callback): void
    {
        Signals::when_available(function () use ($signals, $callback): void {
            $this->signals ??= new Signals($this->get_application()->get_signal_registry());
            Collection::wrap(value($signals))->each(fn($signal) => $this->signals->register($signal, $callback));
        });
    }
    /**
     * Untrap signal handlers set within the command's handler.
     *
     *
     * @internal
     */
    public function untrap(): void
    {
        if (!is_null($this->signals)) {
            $this->signals->unregister();
            $this->signals = null;
        }
    }
}