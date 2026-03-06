<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Support\Str;

trait HasVersion4Uuids
{
    use HasUuids;

    /**
     * Generate a new UUID (version 4) for the model.
     */
    public function newUniqueId(): string
    {
        return (string) Str::orderedUuid();
    }
}
