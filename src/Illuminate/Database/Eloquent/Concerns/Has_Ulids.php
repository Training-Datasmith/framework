<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Support\Str;
trait Has_Ulids
{
    use Has_Unique_String_Ids;
    /**
     * Generate a new unique key for the model.
     */
    public function new_unique_id(): string
    {
        return strtolower((string) Str::ulid());
    }
    /**
     * Determine if given key is valid.
     *
     * @param  mixed  $value
     */
    protected function is_valid_unique_id($value): bool
    {
        return Str::is_ulid($value);
    }
}