<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

use Illuminate\Contracts\Database\Events\Migration_Event as MigrationEventContract;
use Illuminate\Database\Migrations\Migration;
abstract class Migration_Event implements Migration_Event_Contract
{
    /**
     * A migration instance.
     *
     * @var \Illuminate\Database\Migrations\Migration
     */
    public $migration;
    /**
     * Create a new event instance.
     *
     * @param  string  $method
     */
    public function __construct(
        Migration $migration,
        /**
         * The migration method that was called.
         */
        public $method
    )
    {
        $this->migration = $migration;
    }
}