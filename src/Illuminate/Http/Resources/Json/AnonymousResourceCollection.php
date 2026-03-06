<?php

declare(strict_types=1);

namespace Illuminate\Http\Resources\Json;

class AnonymousResourceCollection extends ResourceCollection
{
    /**
     * Indicates if the collection keys should be preserved.
     *
     * @var bool
     */
    public $preserveKeys = false;

    /**
     * Create a new anonymous resource collection.
     *
     * @param  mixed  $resource
     * @param  string  $collects
     */
    public function __construct($resource, /**
     * The name of the resource being collected.
     */
        public $collects)
    {
        parent::__construct($resource);
    }

    /**
     * Indicate that the collection keys should be preserved.
     */
    public function preserveKeys(bool $value = true): static
    {
        $this->preserveKeys = $value;

        return $this;
    }
}
