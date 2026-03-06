<?php

namespace Illuminate\Support;

use Illuminate\Contracts\Support\Htmlable;
use Stringable;

class HtmlString implements Htmlable, Stringable
{
    /**
     * Create a new HTML string instance.
     *
     * @param  string  $html
     */
    public function __construct(
        /**
         * The HTML string.
         */
        protected $html = ''
    )
    {
    }

    /**
     * Get the HTML string.
     *
     * @return string
     */
    public function toHtml()
    {
        return $this->html;
    }

    /**
     * Determine if the given HTML string is empty.
     */
    public function isEmpty(): bool
    {
        return ($this->html ?? '') === '';
    }

    /**
     * Determine if the given HTML string is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the HTML string.
     */
    public function __toString(): string
    {
        return $this->toHtml() ?? '';
    }
}
