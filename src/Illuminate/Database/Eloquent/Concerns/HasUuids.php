<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Support\Str;

trait HasUuids
{
    use HasUniqueStringIds;

    /**
     * Generate a new unique key for the model.
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Determine if given key is valid.
     *
     * @param  mixed  $value
     */
    protected function isValidUniqueId($value): bool
    {
        return Str::isUuid($value);
    }
}
