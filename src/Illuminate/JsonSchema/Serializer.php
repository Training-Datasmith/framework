<?php

declare (strict_types=1);
namespace Illuminate\Json_Schema;

use RuntimeException;
class Serializer
{
    /**
     * The properties to ignore when serializing.
     *
     * @var array<int, string>
     */
    protected static array $ignore = ['required', 'nullable'];
    /**
     * Serialize the given property to an array.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public static function serialize(Types\Type $type): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (fn(): array => get_object_vars($type))->call($type);
        $attributes['type'] = match ($type::class) {
            Types\Array_Type::class => 'array',
            Types\Boolean_Type::class => 'boolean',
            Types\Integer_Type::class => 'integer',
            Types\Number_Type::class => 'number',
            Types\Object_Type::class => 'object',
            Types\String_Type::class => 'string',
            default => throw new RuntimeException('Unsupported [' . $type::class . '] type.'),
        };
        $nullable = static::is_nullable($type);
        if ($nullable) {
            $attributes['type'] = [$attributes['type'], 'null'];
        }
        $attributes = array_filter($attributes, static function (mixed $value, string $key): bool {
            if (in_array($key, static::$ignore, true)) {
                return false;
            }
            return $value !== null;
        }, ARRAY_FILTER_USE_BOTH);
        if ($type instanceof Types\Object_Type) {
            if (count($attributes['properties']) === 0) {
                unset($attributes['properties']);
            } else {
                $required = array_keys(array_filter($attributes['properties'], static::is_required(...)));
                if (count($required) > 0) {
                    $attributes['required'] = $required;
                }
                $attributes['properties'] = array_map(static::serialize(...), $attributes['properties']);
            }
        }
        if ($type instanceof Types\Array_Type) {
            if (isset($attributes['items']) && $attributes['items'] instanceof Types\Type) {
                $attributes['items'] = static::serialize($attributes['items']);
            }
        }
        return $attributes;
    }
    /**
     * Determine if the given type is required.
     */
    protected static function is_required(Types\Type $type): bool
    {
        $attributes = (fn(): array => get_object_vars($type))->call($type);
        return isset($attributes['required']) && $attributes['required'] === true;
    }
    /**
     * Determine if the given type is nullable.
     */
    protected static function is_nullable(Types\Type $type): bool
    {
        $attributes = (fn(): array => get_object_vars($type))->call($type);
        return isset($attributes['nullable']) && $attributes['nullable'] === true;
    }
}