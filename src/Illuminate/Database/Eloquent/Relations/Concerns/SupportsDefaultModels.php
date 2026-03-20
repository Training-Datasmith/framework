<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;
trait Supports_Default_Models
{
    /**
     * Indicates if a default model instance should be used.
     *
     * Alternatively, may be a Closure or array.
     *
     * @var \Closure|array|bool
     */
    protected $with_default;
    /**
     * Make a new related instance for the given model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    abstract protected function new_related_instance_for(Model $parent);
    /**
     * Return a new model instance in case the relationship does not exist.
     *
     * @param  \Closure|array|bool  $callback
     * @return $this
     */
    public function with_default($callback = true)
    {
        $this->with_default = $callback;
        return $this;
    }
    /**
     * Get the default value for this relation.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function get_default_for(Model $parent)
    {
        if (!$this->with_default) {
            return;
        }
        $instance = $this->new_related_instance_for($parent);
        if (is_callable($this->with_default)) {
            return call_user_func($this->with_default, $instance, $parent) ?: $instance;
        }
        if (is_array($this->with_default)) {
            $instance->force_fill($this->with_default);
        }
        return $instance;
    }
}