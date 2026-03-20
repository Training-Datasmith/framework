<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions;

use Illuminate\Support\Traits\Reflects_Closures;
use Throwable;
class Reportable_Handler
{
    use Reflects_Closures;
    /**
     * The underlying callback.
     *
     * @var callable
     */
    protected $callback;
    /**
     * Indicates if reporting should stop after invoking this handler.
     *
     * @var bool
     */
    protected $should_stop = false;
    /**
     * Create a new reportable handler instance.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }
    /**
     * Invoke the handler.
     *
     * @return bool
     */
    public function __invoke(Throwable $e)
    {
        $result = call_user_func($this->callback, $e);
        if ($result === false) {
            return false;
        }
        return !$this->should_stop;
    }
    /**
     * Determine if the callback handles the given exception.
     */
    public function handles(Throwable $e): bool
    {
        foreach ($this->first_closure_parameter_types($this->callback) as $type) {
            if (is_a($e, $type)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Indicate that report handling should stop after invoking this callback.
     *
     * @return $this
     */
    public function stop(): static
    {
        $this->should_stop = true;
        return $this;
    }
}