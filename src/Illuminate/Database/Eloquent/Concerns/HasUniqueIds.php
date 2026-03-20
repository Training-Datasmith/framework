<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

trait Has_Unique_Ids
{
    /**
     * Indicates if the model uses unique ids.
     *
     * @var bool
     */
    public $uses_unique_ids = false;
    /**
     * Determine if the model uses unique ids.
     *
     * @return bool
     */
    public function uses_unique_ids()
    {
        return $this->uses_unique_ids;
    }
    /**
     * Generate unique keys for the model.
     */
    public function set_unique_ids(): void
    {
        foreach ($this->unique_ids() as $column) {
            if (empty($this->{$column})) {
                $this->{$column} = $this->new_unique_id();
            }
        }
    }
    /**
     * Generate a new key for the model.
     *
     * @return string
     */
    public function new_unique_id(): null
    {
        return null;
    }
    /**
     * Get the columns that should receive a unique identifier.
     */
    public function unique_ids(): array
    {
        return [];
    }
}