<?php

namespace Illuminate\View;

use Stringable;

class AppendableAttributeValue implements Stringable
{
    /**
     * Create a new appendable attribute value.
     *
     * @param  mixed  $value
     */
    public function __construct(
        /**
         * The attribute value.
         */
        public $value
    )
    {
    }

    /**
     * Get the string value.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}
