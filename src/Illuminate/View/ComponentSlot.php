<?php

declare(strict_types=1);

namespace Illuminate\View;

use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use Stringable;

class ComponentSlot implements Htmlable, Stringable
{
    /**
     * The slot attribute bag.
     *
     * @var \Illuminate\View\ComponentAttributeBag
     */
    public $attributes;

    /**
     * Create a new slot instance.
     *
     * @param  string  $contents
     */
    public function __construct(/**
     * The slot contents.
     */
        protected $contents = '',
        array $attributes = []
    ) {
        $this->withAttributes($attributes);
    }

    /**
     * Set the extra attributes that the slot should make available.
     *
     * @return $this
     */
    public function withAttributes(array $attributes): static
    {
        $this->attributes = new ComponentAttributeBag($attributes);

        return $this;
    }

    /**
     * Get the slot's HTML string.
     *
     * @return string
     */
    public function toHtml()
    {
        return $this->contents;
    }

    /**
     * Determine if the slot is empty.
     */
    public function isEmpty(): bool
    {
        return $this->contents === '';
    }

    /**
     * Determine if the slot is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Determine if the slot has non-comment content.
     */
    public function hasActualContent(callable|string|null $callable = null): bool
    {
        if (is_string($callable) && ! function_exists($callable)) {
            throw new InvalidArgumentException('Callable does not exist.');
        }

        return filter_var(
            $this->contents,
            FILTER_CALLBACK,
            ['options' => $callable ?? fn ($input): string => trim((string) preg_replace("/<!--([\s\S]*?)-->/", '', (string) $input))]
        ) !== '';
    }

    /**
     * Get the slot's HTML string.
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }
}
