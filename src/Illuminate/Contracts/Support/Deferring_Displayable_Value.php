<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Support;

interface Deferring_Displayable_Value
{
    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \Illuminate\Contracts\Support\Htmlable|string
     */
    public function resolve_displayable_value();
}