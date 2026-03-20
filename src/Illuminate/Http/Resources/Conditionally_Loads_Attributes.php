<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\Stringable;
trait Conditionally_Loads_Attributes
{
    /**
     * Filter the given data, removing any optional values.
     *
     * @return array
     */
    protected function filter(array $data)
    {
        $index = -1;
        foreach ($data as $key => $value) {
            $index++;
            if (is_array($value)) {
                $data[$key] = $this->filter($value);
                continue;
            }
            if (is_numeric($key) && $value instanceof Merge_Value) {
                return $this->merge_data($data, $index, $this->filter($value->data), array_values($value->data) === $value->data);
            }
            if ($value instanceof self && is_null($value->resource)) {
                $data[$key] = null;
            }
        }
        return $this->remove_missing_values($data);
    }
    /**
     * Merge the given data in at the given index.
     *
     * @param  array  $data
     * @param  int  $index
     * @param  array  $merge
     * @param  bool  $numericKeys
     * @return array
     */
    protected function merge_data($data, $index, $merge, $numeric_keys)
    {
        if ($numeric_keys) {
            return $this->remove_missing_values(array_merge(array_merge(array_slice($data, 0, $index, true), $merge), $this->filter(array_values(array_slice($data, $index + 1, null, true)))));
        }
        return $this->remove_missing_values(array_slice($data, 0, $index, true) + $merge + $this->filter(array_slice($data, $index + 1, null, true)));
    }
    /**
     * Remove the missing values from the filtered data.
     *
     * @return array
     */
    protected function remove_missing_values(array $data)
    {
        $numeric_keys = true;
        foreach ($data as $key => $value) {
            if ($value instanceof Potentially_Missing && $value->is_missing() || $value instanceof self && $value->resource instanceof Potentially_Missing && $value->is_missing()) {
                unset($data[$key]);
            } else {
                $numeric_keys = $numeric_keys && is_numeric($key);
            }
        }
        if (property_exists($this, 'preserveKeys') && $this->preserve_keys === true) {
            return $data;
        }
        return $numeric_keys ? array_values($data) : $data;
    }
    /**
     * Retrieve a value if the given "condition" is truthy.
     *
     * @param  bool  $condition
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function when($condition, $value, $default = new Missing_Value())
    {
        if ($condition) {
            return value($value);
        }
        return func_num_args() === 3 ? value($default) : $default;
    }
    /**
     * Retrieve a value if the given "condition" is falsy.
     *
     * @param  bool  $condition
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function unless($condition, $value, $default = new Missing_Value())
    {
        $arguments = func_num_args() === 2 ? [$value] : [$value, $default];
        return $this->when(!$condition, ...$arguments);
    }
    /**
     * Merge a value into the array.
     *
     * @param  mixed  $value
     * @return \Illuminate\Http\Resources\MergeValue|mixed
     */
    protected function merge($value)
    {
        return $this->merge_when(true, $value);
    }
    /**
     * Merge a value if the given condition is truthy.
     *
     * @param  bool  $condition
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MergeValue|mixed
     */
    protected function merge_when($condition, $value, $default = new Missing_Value())
    {
        if ($condition) {
            return new Merge_Value(value($value));
        }
        return func_num_args() === 3 ? new Merge_Value(value($default)) : $default;
    }
    /**
     * Merge a value unless the given condition is truthy.
     *
     * @param  bool  $condition
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MergeValue|mixed
     */
    protected function merge_unless($condition, $value, $default = new Missing_Value())
    {
        $arguments = func_num_args() === 2 ? [$value] : [$value, $default];
        return $this->merge_when(!$condition, ...$arguments);
    }
    /**
     * Merge the given attributes.
     *
     * @param  array  $attributes
     */
    protected function attributes($attributes): \Illuminate\Http\Resources\Merge_Value
    {
        return new Merge_Value(Arr::only($this->resource->to_array(), $attributes));
    }
    /**
     * Retrieve an attribute if it exists on the resource.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function when_has($attribute, $value = null, $default = new Missing_Value())
    {
        if (!array_key_exists($attribute, $this->resource->get_attributes())) {
            return value($default);
        }
        return func_num_args() === 1 ? $this->resource->{$attribute} : value($value, $this->resource->{$attribute});
    }
    /**
     * Retrieve a model attribute if it is null.
     *
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function when_null($value, $default = new Missing_Value())
    {
        $arguments = func_num_args() == 1 ? [$value] : [$value, $default];
        return $this->when(is_null($value), ...$arguments);
    }
    /**
     * Retrieve a model attribute if it is not null.
     *
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function when_not_null($value, $default = new Missing_Value())
    {
        $arguments = func_num_args() == 1 ? [$value] : [$value, $default];
        return $this->when(!is_null($value), ...$arguments);
    }
    /**
     * Retrieve an accessor when it has been appended.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function when_appended($attribute, $value = null, $default = new Missing_Value())
    {
        if ($this->resource->has_appended($attribute)) {
            return func_num_args() >= 2 ? value($value) : $this->resource->{$attribute};
        }
        return func_num_args() === 3 ? value($default) : $default;
    }
    /**
     * Retrieve a relationship if it has been loaded.
     *
     * @param  string  $relationship
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function when_loaded($relationship, $value = null, $default = new Missing_Value())
    {
        if (!$this->resource->relation_loaded($relationship)) {
            return value($default);
        }
        $loaded_value = $this->resource->{$relationship};
        if (func_num_args() === 1) {
            return $loaded_value;
        }
        if ($loaded_value === null) {
            return;
        }
        if ($value === null) {
            $value = value(...);
        }
        return value($value, $loaded_value);
    }
    /**
     * Retrieve a relationship count if it exists.
     *
     * @param  string  $relationship
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function when_counted($relationship, $value = null, $default = new Missing_Value())
    {
        $attribute = (new Stringable($relationship))->snake()->finish('_count')->value();
        if (!array_key_exists($attribute, $this->resource->get_attributes())) {
            return value($default);
        }
        if (func_num_args() === 1) {
            return $this->resource->{$attribute};
        }
        if ($this->resource->{$attribute} === null) {
            return;
        }
        if ($value === null) {
            $value = value(...);
        }
        return value($value, $this->resource->{$attribute});
    }
    /**
     * Retrieve a relationship aggregated value if it exists.
     *
     * @param  string  $relationship
     * @param  string  $column
     * @param  string  $aggregate
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function when_aggregated($relationship, $column, $aggregate, $value = null, $default = new Missing_Value())
    {
        $attribute = (new Stringable($relationship))->snake()->append('_')->append($aggregate)->append('_')->finish($column)->value();
        if (!array_key_exists($attribute, $this->resource->get_attributes())) {
            return value($default);
        }
        if (func_num_args() === 3) {
            return $this->resource->{$attribute};
        }
        if ($this->resource->{$attribute} === null) {
            return;
        }
        if ($value === null) {
            $value = value(...);
        }
        return value($value, $this->resource->{$attribute});
    }
    /**
     * Retrieve a relationship existence check if it exists.
     *
     * @param  string  $relationship
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    public function when_exists_loaded($relationship, $value = null, $default = new Missing_Value())
    {
        $attribute = (new Stringable($relationship))->snake()->finish('_exists')->value();
        if (!array_key_exists($attribute, $this->resource->get_attributes())) {
            return value($default);
        }
        if (func_num_args() === 1) {
            return $this->resource->{$attribute};
        }
        if ($this->resource->{$attribute} === null) {
            return;
        }
        return value($value, $this->resource->{$attribute});
    }
    /**
     * Execute a callback if the given pivot table has been loaded.
     *
     * @param  string  $table
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function when_pivot_loaded($table, $value, $default = new Missing_Value())
    {
        return $this->when_pivot_loaded_as('pivot', ...func_get_args());
    }
    /**
     * Execute a callback if the given pivot table with a custom accessor has been loaded.
     *
     * @param  string  $accessor
     * @param  string  $table
     * @param  mixed  $value
     * @param  mixed  $default
     * @return \Illuminate\Http\Resources\MissingValue|mixed
     */
    protected function when_pivot_loaded_as($accessor, $table, $value, $default = new Missing_Value())
    {
        return $this->when($this->has_pivot_loaded_as($accessor, $table), $value, $default);
    }
    /**
     * Determine if the resource has the specified pivot table loaded.
     *
     * @param  string  $table
     * @return bool
     */
    protected function has_pivot_loaded($table)
    {
        return $this->has_pivot_loaded_as('pivot', $table);
    }
    /**
     * Determine if the resource has the specified pivot table loaded with a custom accessor.
     *
     * @param  string  $accessor
     * @param  string  $table
     */
    protected function has_pivot_loaded_as($accessor, $table): bool
    {
        return isset($this->resource->{$accessor}) && ($this->resource->{$accessor} instanceof $table || $this->resource->{$accessor}->get_table() === $table);
    }
    /**
     * Transform the given value if it is present.
     *
     * @param  mixed  $value
     * @param  mixed  $default
     * @return mixed
     */
    protected function transform($value, callable $callback, $default = new Missing_Value())
    {
        return transform($value, $callback, $default);
    }
}