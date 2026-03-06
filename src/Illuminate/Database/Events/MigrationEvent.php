<?php

declare(strict_types=1);

namespace Illuminate\Database\Events;

use Illuminate\Contracts\Database\Events\MigrationEvent as MigrationEventContract;
use Illuminate\Database\Migrations\Migration;

abstract class MigrationEvent implements MigrationEventContract
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
    public function __construct(Migration $migration, /**
     * The migration method that was called.
     */
        public $method)
    {
        $this->migration = $migration;
    }
}
