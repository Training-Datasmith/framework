<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\Casts_Attributes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
class As_Collection implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @return \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\Illuminate\Support\Collection<array-key, mixed>, iterable>
     */
    public static function cast_using(array $arguments): \Illuminate\Contracts\Database\Eloquent\Casts_Attributes
    {
        return new class($arguments) implements Casts_Attributes
        {
            public function __construct(protected array $arguments)
            {
                $this->arguments = array_pad(array_values($this->arguments), 2, '');
            }
            public function get($model, $key, $value, $attributes)
            {
                if (!isset($attributes[$key])) {
                    return;
                }
                $data = Json::decode($attributes[$key]);
                $collection_class = empty($this->arguments[0]) ? Collection::class : $this->arguments[0];
                if (!is_a($collection_class, Collection::class, true)) {
                    throw new InvalidArgumentException('The provided class must extend [' . Collection::class . '].');
                }
                if (!is_array($data)) {
                    return null;
                }
                $instance = new $collection_class($data);
                if (!isset($this->arguments[1]) || !$this->arguments[1]) {
                    return $instance;
                }
                if (is_string($this->arguments[1])) {
                    $this->arguments[1] = Str::parse_callback($this->arguments[1]);
                }
                return is_callable($this->arguments[1]) ? $instance->map($this->arguments[1]) : $instance->map_into($this->arguments[1][0]);
            }
            public function set($model, $key, $value, $attributes): array
            {
                return [$key => Json::encode($value)];
            }
        };
    }
    /**
     * Specify the type of object each item in the collection should be mapped to.
     *
     * @param  array{class-string, string}|class-string  $map
     */
    public static function of($map): string
    {
        return static::using('', $map);
    }
    /**
     * Specify the collection type for the cast.
     *
     * @param  class-string  $class
     * @param  array{class-string, string}|class-string|null  $map
     */
    public static function using($class, $map = null): string
    {
        if (is_array($map) && is_callable($map)) {
            $map = $map[0] . '@' . $map[1];
        }
        return static::class . ':' . implode(',', [$class, $map]);
    }
}