<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json;

class Anonymous_Resource_Collection extends Resource_Collection
{
    /**
     * Indicates if the collection keys should be preserved.
     *
     * @var bool
     */
    public $preserve_keys = false;
    /**
     * Create a new anonymous resource collection.
     *
     * @param  mixed  $resource
     * @param  string  $collects
     */
    public function __construct(
        $resource,
        /**
         * The name of the resource being collected.
         */
        public $collects
    )
    {
        parent::__construct($resource);
    }
    /**
     * Indicate that the collection keys should be preserved.
     */
    public function preserve_keys(bool $value = true): static
    {
        $this->preserve_keys = $value;
        return $this;
    }
}