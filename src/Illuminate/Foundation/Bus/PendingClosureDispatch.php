<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bus;

use Closure;
class Pending_Closure_Dispatch extends Pending_Dispatch
{
    /**
     * Add a callback to be executed if the job fails.
     *
     * @return $this
     */
    public function catch(Closure $callback): static
    {
        $this->job->on_failure($callback);
        return $this;
    }
}