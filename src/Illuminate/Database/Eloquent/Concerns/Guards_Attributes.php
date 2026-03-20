<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

trait Guards_Attributes
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [];
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = ['*'];
    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = false;
    /**
     * The actual columns that exist on the database and can be guarded.
     *
     * @var array<class-string,list<string>>
     */
    protected static $guardable_columns = [];
    /**
     * Get the fillable attributes for the model.
     *
     * @return array<string>
     */
    public function get_fillable()
    {
        return $this->fillable;
    }
    /**
     * Set the fillable attributes for the model.
     *
     * @param  array<string>  $fillable
     * @return $this
     */
    public function fillable(array $fillable)
    {
        $this->fillable = $fillable;
        return $this;
    }
    /**
     * Merge new fillable attributes with existing fillable attributes on the model.
     *
     * @param  array<string>  $fillable
     * @return $this
     */
    public function merge_fillable(array $fillable)
    {
        $this->fillable = array_values(array_unique(array_merge($this->fillable, $fillable)));
        return $this;
    }
    /**
     * Get the guarded attributes for the model.
     *
     * @return array<string>
     */
    public function get_guarded()
    {
        return self::$unguarded === true ? [] : $this->guarded;
    }
    /**
     * Set the guarded attributes for the model.
     *
     * @param  array<string>  $guarded
     * @return $this
     */
    public function guard(array $guarded)
    {
        $this->guarded = $guarded;
        return $this;
    }
    /**
     * Merge new guarded attributes with existing guarded attributes on the model.
     *
     * @param  array<string>  $guarded
     * @return $this
     */
    public function merge_guarded(array $guarded)
    {
        $this->guarded = array_values(array_unique(array_merge($this->guarded, $guarded)));
        return $this;
    }
    /**
     * Disable all mass assignable restrictions.
     *
     * @param  bool  $state
     */
    public static function unguard($state = true): void
    {
        static::$unguarded = $state;
    }
    /**
     * Enable the mass assignment restrictions.
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }
    /**
     * Determine if the current state is "unguarded".
     *
     * @return bool
     */
    public static function is_unguarded()
    {
        return static::$unguarded;
    }
    /**
     * Run the given callable while being unguarded.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function unguarded(callable $callback)
    {
        if (static::$unguarded) {
            return $callback();
        }
        static::unguard();
        try {
            return $callback();
        } finally {
            static::reguard();
        }
    }
    /**
     * Determine if the given attribute may be mass assigned.
     *
     * @param  string  $key
     * @return bool
     */
    public function is_fillable($key)
    {
        if (static::$unguarded) {
            return true;
        }
        // If the key is in the "fillable" array, we can of course assume that it's
        // a fillable attribute. Otherwise, we will check the guarded array when
        // we need to determine if the attribute is black-listed on the model.
        if (in_array($key, $this->get_fillable())) {
            return true;
        }
        // If the attribute is explicitly listed in the "guarded" array then we can
        // return false immediately. This means this attribute is definitely not
        // fillable and there is no point in going any further in this method.
        if ($this->is_guarded($key)) {
            return false;
        }
        return empty($this->get_fillable()) && !str_contains($key, '.') && !str_starts_with($key, '_');
    }
    /**
     * Determine if the given key is guarded.
     *
     * @param  string  $key
     * @return bool
     */
    public function is_guarded($key)
    {
        if (empty($this->get_guarded())) {
            return false;
        }
        if ($this->get_guarded() == ['*']) {
            return true;
        }
        if (!empty(preg_grep('/^' . preg_quote($key, '/') . '$/i', $this->get_guarded()))) {
            return true;
        }
        return !$this->is_guardable_column($key);
    }
    /**
     * Determine if the given column is a valid, guardable column.
     *
     * @param  string  $key
     * @return bool
     */
    protected function is_guardable_column($key)
    {
        if ($this->has_set_mutator($key) || $this->has_attribute_set_mutator($key) || $this->is_class_castable($key)) {
            return true;
        }
        if (!isset(static::$guardable_columns[$this::class])) {
            $columns = $this->get_connection()->get_schema_builder()->get_column_listing($this->get_table());
            if (empty($columns)) {
                return true;
            }
            static::$guardable_columns[$this::class] = $columns;
        }
        return in_array($key, static::$guardable_columns[$this::class]);
    }
    /**
     * Determine if the model is totally guarded.
     */
    public function totally_guarded(): bool
    {
        return count($this->get_fillable()) === 0 && $this->get_guarded() == ['*'];
    }
    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function fillable_from_array(array $attributes): array
    {
        if (count($this->get_fillable()) > 0 && !static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->get_fillable()));
        }
        return $attributes;
    }
}