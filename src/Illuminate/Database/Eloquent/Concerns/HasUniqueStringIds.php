<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model_Not_Found_Exception;
trait Has_Unique_String_Ids
{
    /**
     * Generate a new unique key for the model.
     *
     * @return mixed
     */
    abstract public function new_unique_id();
    /**
     * Determine if given key is valid.
     *
     * @param  mixed  $value
     */
    abstract protected function is_valid_unique_id($value): bool;
    /**
     * Initialize the trait.
     */
    public function initialize_has_unique_string_ids(): void
    {
        $this->uses_unique_ids = true;
    }
    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array
     */
    public function unique_ids()
    {
        return $this->uses_unique_ids() ? [$this->get_key_name()] : parent::unique_ids();
    }
    /**
     * Retrieve the model for a bound value.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function resolve_route_binding_query($query, $value, $field = null)
    {
        if ($field && in_array($field, $this->unique_ids()) && !$this->is_valid_unique_id($value)) {
            $this->handle_invalid_unique_id($value, $field);
        }
        if (!$field && in_array($this->get_route_key_name(), $this->unique_ids()) && !$this->is_valid_unique_id($value)) {
            $this->handle_invalid_unique_id($value, $field);
        }
        return parent::resolve_route_binding_query($query, $value, $field);
    }
    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function get_key_type()
    {
        if (in_array($this->get_key_name(), $this->unique_ids())) {
            return 'string';
        }
        return parent::get_key_type();
    }
    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function get_incrementing()
    {
        if (in_array($this->get_key_name(), $this->unique_ids())) {
            return false;
        }
        return parent::get_incrementing();
    }
    /**
     * Throw an exception for the given invalid unique ID.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function handle_invalid_unique_id($value, $field): never
    {
        throw (new Model_Not_Found_Exception())->set_model($this::class, $value);
    }
}