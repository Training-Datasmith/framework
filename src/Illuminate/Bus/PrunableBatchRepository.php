<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use DateTimeInterface;
interface Prunable_Batch_Repository extends Batch_Repository
{
    /**
     * Prune all of the entries older than the given date.
     *
     * @return int
     */
    public function prune(DateTimeInterface $before);
}