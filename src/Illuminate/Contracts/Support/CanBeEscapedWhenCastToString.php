<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Support;

interface Can_Be_Escaped_When_Cast_To_String
{
    /**
     * Indicate that the object's string representation should be escaped when __toString is invoked.
     *
     * @param  bool  $escape
     * @return $this
     */
    public function escape_when_casting_to_string($escape = true);
}