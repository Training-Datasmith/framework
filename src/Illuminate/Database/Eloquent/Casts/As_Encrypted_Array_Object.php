<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\Casts_Attributes;
use Illuminate\Support\Facades\Crypt;
class As_Encrypted_Array_Object implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @return \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\Illuminate\Database\Eloquent\Casts\ArrayObject<array-key, mixed>, iterable>
     */
    public static function cast_using(array $arguments): \Illuminate\Contracts\Database\Eloquent\Casts_Attributes
    {
        return new class implements Casts_Attributes
        {
            public function get($model, $key, $value, $attributes): ?\Illuminate\Database\Eloquent\Casts\ArrayObject
            {
                if (isset($attributes[$key])) {
                    return new ArrayObject(Json::decode(Crypt::decrypt_string($attributes[$key])), ArrayObject::ARRAY_AS_PROPS);
                }
                return null;
            }
            public function set($model, $key, $value, $attributes): ?array
            {
                if (!is_null($value)) {
                    return [$key => Crypt::encrypt_string(Json::encode($value))];
                }
                return null;
            }
            public function serialize($model, string $key, $value, array $attributes)
            {
                return !is_null($value) ? $value->get_array_copy() : null;
            }
        };
    }
}