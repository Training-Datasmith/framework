<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Support\Str;

trait HasUlids
{
    use HasUniqueStringIds;

    /**
     * Generate a new unique key for the model.
     */
    public function newUniqueId(): string
    {
        return strtolower((string) Str::ulid());
    }

    /**
     * Determine if given key is valid.
     *
     * @param  mixed  $value
     */
    protected function isValidUniqueId($value): bool
    {
        return Str::isUlid($value);
    }
}
