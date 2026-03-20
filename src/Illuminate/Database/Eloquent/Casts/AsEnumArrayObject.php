<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Casts;

use Backed_Enum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\Casts_Attributes;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
class As_Enum_Array_Object implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @template TEnum of \UnitEnum
     *
     * @param  array{class-string<TEnum>}  $arguments
     * @return \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\Illuminate\Database\Eloquent\Casts\ArrayObject<array-key, TEnum>, iterable<TEnum>>
     */
    public static function cast_using(array $arguments): \Illuminate\Contracts\Database\Eloquent\Casts_Attributes
    {
        return new class($arguments) implements Casts_Attributes
        {
            public function __construct(protected array $arguments)
            {
            }
            public function get($model, $key, $value, $attributes)
            {
                if (!isset($attributes[$key])) {
                    return;
                }
                $data = Json::decode($attributes[$key]);
                if (!is_array($data)) {
                    return;
                }
                $enum_class = $this->arguments[0];
                return new ArrayObject((new Collection($data))->map(fn($value): mixed => is_subclass_of($enum_class, Backed_Enum::class) ? $enum_class::from($value) : constant($enum_class . '::' . $value))->to_array());
            }
            /**
             * @return mixed[]
             */
            public function set($model, $key, $value, $attributes): array
            {
                if ($value === null) {
                    return [$key => null];
                }
                $storable = [];
                foreach ($value as $enum) {
                    $storable[] = $this->get_storable_enum_value($enum);
                }
                return [$key => Json::encode($storable)];
            }
            public function serialize($model, string $key, $value, array $attributes)
            {
                return (new Collection($value->get_array_copy()))->map(fn($enum) => $this->get_storable_enum_value($enum))->to_array();
            }
            protected function get_storable_enum_value($enum)
            {
                if (is_string($enum) || is_int($enum)) {
                    return $enum;
                }
                return enum_value($enum);
            }
        };
    }
    /**
     * Specify the Enum for the cast.
     *
     * @param  class-string  $class
     */
    public static function of(string $class): string
    {
        return static::class . ':' . $class;
    }
}