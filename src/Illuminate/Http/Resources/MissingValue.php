<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources;

class Missing_Value implements Potentially_Missing
{
    /**
     * Determine if the object should be considered "missing".
     */
    public function is_missing(): bool
    {
        return true;
    }
}