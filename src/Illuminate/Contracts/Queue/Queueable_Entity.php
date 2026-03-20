<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Queue;

interface Queueable_Entity
{
    /**
     * Get the queueable identity for the entity.
     *
     * @return mixed
     */
    public function get_queueable_id();
    /**
     * Get the relationships for the entity.
     *
     * @return array
     */
    public function get_queueable_relations();
    /**
     * Get the connection of the entity.
     *
     * @return string|null
     */
    public function get_queueable_connection();
}