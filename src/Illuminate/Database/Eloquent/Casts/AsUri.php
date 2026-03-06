<?php

namespace Illuminate\Database\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Uri;

class AsUri implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @return \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\Illuminate\Support\Uri, string|Uri>
     */
    public static function castUsing(array $arguments): \Illuminate\Contracts\Database\Eloquent\CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, $key, $value, $attributes): ?\Illuminate\Support\Uri
            {
                return isset($value) ? new Uri($value) : null;
            }

            public function set($model, $key, $value, $attributes): ?string
            {
                return isset($value) ? (string) $value : null;
            }
        };
    }
}
