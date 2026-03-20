<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Support\Facades\Date;
trait Has_Timestamps
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;
    /**
     * The list of models classes that have timestamps temporarily disabled.
     *
     * @var array
     */
    protected static $ignore_timestamps_on = [];
    /**
     * Update the model's update timestamp.
     *
     * @param  string|null  $attribute
     * @return bool
     */
    public function touch($attribute = null)
    {
        if ($attribute) {
            $this->{$attribute} = $this->fresh_timestamp();
            return $this->save();
        }
        if (!$this->uses_timestamps()) {
            return false;
        }
        $this->update_timestamps();
        return $this->save();
    }
    /**
     * Update the model's update timestamp without raising any events.
     *
     * @param  string|null  $attribute
     * @return bool
     */
    public function touch_quietly($attribute = null)
    {
        return static::without_events(fn() => $this->touch($attribute));
    }
    /**
     * Update the creation and update timestamps.
     *
     * @return $this
     */
    public function update_timestamps()
    {
        $time = $this->fresh_timestamp();
        $updated_at_column = $this->get_updated_at_column();
        if (!is_null($updated_at_column) && !$this->is_dirty($updated_at_column)) {
            $this->set_updated_at($time);
        }
        $created_at_column = $this->get_created_at_column();
        if (!$this->exists && !is_null($created_at_column) && !$this->is_dirty($created_at_column)) {
            $this->set_created_at($time);
        }
        return $this;
    }
    /**
     * Set the value of the "created at" attribute.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function set_created_at($value)
    {
        $this->{$this->get_created_at_column()} = $value;
        return $this;
    }
    /**
     * Set the value of the "updated at" attribute.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function set_updated_at($value)
    {
        $this->{$this->get_updated_at_column()} = $value;
        return $this;
    }
    /**
     * Get a fresh timestamp for the model.
     *
     * @return \Illuminate\Support\Carbon
     */
    public function fresh_timestamp()
    {
        return Date::now();
    }
    /**
     * Get a fresh timestamp for the model.
     *
     * @return string
     */
    public function fresh_timestamp_string()
    {
        return $this->from_date_time($this->fresh_timestamp());
    }
    /**
     * Determine if the model uses timestamps.
     */
    public function uses_timestamps(): bool
    {
        return $this->timestamps && !static::is_ignoring_timestamps($this::class);
    }
    /**
     * Get the name of the "created at" column.
     *
     * @return string|null
     */
    public function get_created_at_column()
    {
        return static::CREATED_AT;
    }
    /**
     * Get the name of the "updated at" column.
     *
     * @return string|null
     */
    public function get_updated_at_column()
    {
        return static::UPDATED_AT;
    }
    /**
     * Get the fully-qualified "created at" column.
     *
     * @return string|null
     */
    public function get_qualified_created_at_column()
    {
        $column = $this->get_created_at_column();
        return $column ? $this->qualify_column($column) : null;
    }
    /**
     * Get the fully-qualified "updated at" column.
     *
     * @return string|null
     */
    public function get_qualified_updated_at_column()
    {
        $column = $this->get_updated_at_column();
        return $column ? $this->qualify_column($column) : null;
    }
    /**
     * Disable timestamps for the current class during the given callback scope.
     *
     * @return mixed
     */
    public static function without_timestamps(callable $callback)
    {
        return static::without_timestamps_on([static::class], $callback);
    }
    /**
     * Disable timestamps for the given model classes during the given callback scope.
     *
     * @param  array  $models
     * @param  callable  $callback
     * @return mixed
     */
    public static function without_timestamps_on($models, $callback)
    {
        static::$ignore_timestamps_on = array_values(array_merge(static::$ignore_timestamps_on, $models));
        try {
            return $callback();
        } finally {
            foreach ($models as $model) {
                if (($key = array_search($model, static::$ignore_timestamps_on, true)) !== false) {
                    unset(static::$ignore_timestamps_on[$key]);
                }
            }
        }
    }
    /**
     * Determine if the given model is ignoring timestamps / touches.
     *
     * @param  string|null  $class
     */
    public static function is_ignoring_timestamps($class = null): bool
    {
        $class ??= static::class;
        foreach (static::$ignore_timestamps_on as $ignored_class) {
            if ($class === $ignored_class || is_subclass_of($class, $ignored_class)) {
                return true;
            }
        }
        return false;
    }
}