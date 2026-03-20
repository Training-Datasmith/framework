<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Events;

class Publishing_Stubs
{
    use Dispatchable;
    /**
     * The stubs being published.
     *
     * @var array
     */
    public $stubs = [];
    /**
     * Create a new event instance.
     */
    public function __construct(array $stubs)
    {
        $this->stubs = $stubs;
    }
    /**
     * Add a new stub to be published.
     *
     * @return $this
     */
    public function add(string $path, string $name): static
    {
        $this->stubs[$path] = $name;
        return $this;
    }
}