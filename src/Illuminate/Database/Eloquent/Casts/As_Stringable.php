<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\Casts_Attributes;
use Illuminate\Support\Stringable;
class As_Stringable implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @return \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\Illuminate\Support\Stringable, string|\Stringable>
     */
    public static function cast_using(array $arguments): \Illuminate\Contracts\Database\Eloquent\Casts_Attributes
    {
        return new class implements Casts_Attributes
        {
            public function get($model, $key, $value, $attributes): ?\Illuminate\Support\Stringable
            {
                return isset($value) ? new Stringable($value) : null;
            }
            public function set($model, $key, $value, $attributes): ?string
            {
                return isset($value) ? (string) $value : null;
            }
        };
    }
}