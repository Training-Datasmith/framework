<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Queue;

use Illuminate\Bus\Queueable as QueueableByBus;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Interacts_With_Queue;
use Illuminate\Queue\Serializes_Models;
trait Queueable
{
    use Dispatchable;
    use Interacts_With_Queue;
    use Queueable_By_Bus;
    use Serializes_Models;
}