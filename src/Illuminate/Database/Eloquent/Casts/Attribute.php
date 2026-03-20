<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Casts;

class Attribute
{
    /**
     * The attribute accessor.
     *
     * @var callable
     */
    public $get;
    /**
     * The attribute mutator.
     *
     * @var callable
     */
    public $set;
    /**
     * Indicates if caching is enabled for this attribute.
     *
     * @var bool
     */
    public $with_caching = false;
    /**
     * Indicates if caching of objects is enabled for this attribute.
     *
     * @var bool
     */
    public $with_object_caching = true;
    /**
     * Create a new attribute accessor / mutator.
     */
    public function __construct(?callable $get = null, ?callable $set = null)
    {
        $this->get = $get;
        $this->set = $set;
    }
    /**
     * Create a new attribute accessor / mutator.
     */
    public static function make(?callable $get = null, ?callable $set = null): static
    {
        return new static($get, $set);
    }
    /**
     * Create a new attribute accessor.
     */
    public static function get(callable $get): static
    {
        return new static($get);
    }
    /**
     * Create a new attribute mutator.
     */
    public static function set(callable $set): static
    {
        return new static(null, $set);
    }
    /**
     * Disable object caching for the attribute.
     */
    public function without_object_caching(): static
    {
        $this->with_object_caching = false;
        return $this;
    }
    /**
     * Enable caching for the attribute.
     */
    public function should_cache(): static
    {
        $this->with_caching = true;
        return $this;
    }
}