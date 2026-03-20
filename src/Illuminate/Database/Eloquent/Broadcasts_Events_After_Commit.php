<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

trait Broadcasts_Events_After_Commit
{
    use Broadcasts_Events;
    /**
     * Determine if the model event broadcast queued job should be dispatched after all transactions are committed.
     */
    public function broadcast_after_commit(): bool
    {
        return true;
    }
}