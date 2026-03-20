<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Support\Str;
trait Has_Version4uuids
{
    use Has_Uuids;
    /**
     * Generate a new UUID (version 4) for the model.
     */
    public function new_unique_id(): string
    {
        return (string) Str::ordered_uuid();
    }
}