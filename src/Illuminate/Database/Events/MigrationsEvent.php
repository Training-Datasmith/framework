<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

use Illuminate\Contracts\Database\Events\Migration_Event as MigrationEventContract;
abstract class Migrations_Event implements Migration_Event_Contract
{
    /**
     * Create a new event instance.
     *
     * @param  string  $method  The migration method that was invoked.
     * @param  array<string, mixed>  $options  The options provided when the migration method was invoked.
     */
    public function __construct(public $method, public array $options = [])
    {
    }
}