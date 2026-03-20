<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources;

interface Potentially_Missing
{
    /**
     * Determine if the object should be considered "missing".
     *
     * @return bool
     */
    public function is_missing();
}