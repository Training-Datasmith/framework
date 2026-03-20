<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

use Illuminate\Contracts\Database\Events\Migration_Event;
class Migration_Skipped implements Migration_Event
{
    /**
     * Create a new event instance.
     *
     * @param  string  $migrationName  The name of the migration that was skipped.
     */
    public function __construct(public $migration_name)
    {
    }
}