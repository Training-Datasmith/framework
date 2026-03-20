<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

use Illuminate\Contracts\Database\Events\Migration_Event as MigrationEventContract;
class Database_Refreshed implements Migration_Event_Contract
{
    /**
     * Create a new event instance.
     */
    public function __construct(public ?string $database = null, public bool $seeding = false)
    {
    }
}