<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Queue;

interface Queueable_Collection
{
    /**
     * Get the type of the entities being queued.
     *
     * @return string|null
     */
    public function get_queueable_class();
    /**
     * Get the identifiers for all of the entities.
     *
     * @return array<int, mixed>
     */
    public function get_queueable_ids();
    /**
     * Get the relationships of the entities being queued.
     *
     * @return array<int, string>
     */
    public function get_queueable_relations();
    /**
     * Get the connection of the entities being queued.
     *
     * @return string|null
     */
    public function get_queueable_connection();
}