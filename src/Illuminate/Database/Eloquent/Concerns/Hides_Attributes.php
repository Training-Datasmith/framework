<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

trait Hides_Attributes
{
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [];
    /**
     * The attributes that should be visible in serialization.
     *
     * @var array<string>
     */
    protected $visible = [];
    /**
     * Get the hidden attributes for the model.
     *
     * @return array<string>
     */
    public function get_hidden()
    {
        return $this->hidden;
    }
    /**
     * Set the hidden attributes for the model.
     *
     * @param  array<string>  $hidden
     * @return $this
     */
    public function set_hidden(array $hidden)
    {
        $this->hidden = $hidden;
        return $this;
    }
    /**
     * Merge new hidden attributes with existing hidden attributes on the model.
     *
     * @param  array<string>  $hidden
     * @return $this
     */
    public function merge_hidden(array $hidden)
    {
        $this->hidden = array_values(array_unique(array_merge($this->hidden, $hidden)));
        return $this;
    }
    /**
     * Get the visible attributes for the model.
     *
     * @return array<string>
     */
    public function get_visible()
    {
        return $this->visible;
    }
    /**
     * Set the visible attributes for the model.
     *
     * @param  array<string>  $visible
     * @return $this
     */
    public function set_visible(array $visible)
    {
        $this->visible = $visible;
        return $this;
    }
    /**
     * Merge new visible attributes with existing visible attributes on the model.
     *
     * @param  array<string>  $visible
     * @return $this
     */
    public function merge_visible(array $visible)
    {
        $this->visible = array_values(array_unique(array_merge($this->visible, $visible)));
        return $this;
    }
    /**
     * Make the given, typically hidden, attributes visible.
     *
     * @param  array<string>|string|null  $attributes
     * @return $this
     */
    public function make_visible($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->hidden = array_diff($this->hidden, $attributes);
        if (!empty($this->visible)) {
            $this->visible = array_values(array_unique(array_merge($this->visible, $attributes)));
        }
        return $this;
    }
    /**
     * Make the given, typically hidden, attributes visible if the given truth test passes.
     *
     * @param  bool|\Closure  $condition
     * @param  array<string>|string|null  $attributes
     * @return $this
     */
    public function make_visible_if($condition, $attributes)
    {
        return value($condition, $this) ? $this->make_visible($attributes) : $this;
    }
    /**
     * Make the given, typically visible, attributes hidden.
     *
     * @param  array<string>|string|null  $attributes
     * @return $this
     */
    public function make_hidden($attributes)
    {
        $this->hidden = array_values(array_unique(array_merge($this->hidden, is_array($attributes) ? $attributes : func_get_args())));
        return $this;
    }
    /**
     * Make the given, typically visible, attributes hidden if the given truth test passes.
     *
     * @param  bool|\Closure  $condition
     * @param  array<string>|string|null  $attributes
     * @return $this
     */
    public function make_hidden_if($condition, $attributes)
    {
        return value($condition, $this) ? $this->make_hidden($attributes) : $this;
    }
}