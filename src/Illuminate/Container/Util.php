<?php

declare (strict_types=1);
namespace Illuminate\Container;

use Closure;
use Illuminate\Contracts\Container\Contextual_Attribute;
use Reflection_Attribute;
use ReflectionNamedType;
/**
 * @internal
 */
class Util
{
    /**
     * If the given value is not an array and not null, wrap it in one.
     *
     * From Arr::wrap() in Illuminate\Support.
     *
     * @param  mixed  $value
     */
    public static function array_wrap($value): array
    {
        if (is_null($value)) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }
    /**
     * Return the default value of the given value.
     *
     * From global value() helper in Illuminate\Support.
     *
     * @param  mixed  $value
     * @param  mixed  ...$args
     * @return mixed
     */
    public static function unwrap_if_closure($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
    /**
     * Get the class name of the given parameter's type, if possible.
     *
     * From Reflector::getParameterClassName() in Illuminate\Support.
     *
     * @param  \ReflectionParameter  $parameter
     * @return string|null
     */
    public static function get_parameter_class_name($parameter)
    {
        $type = $parameter->get_type();
        if (!$type instanceof ReflectionNamedType || $type->is_builtin()) {
            return null;
        }
        $name = $type->get_name();
        if (!is_null($class = $parameter->get_declaring_class())) {
            if ($name === 'self') {
                return $class->get_name();
            }
            if ($name === 'parent' && $parent = $class->get_parent_class()) {
                return $parent->get_name();
            }
        }
        return $name;
    }
    /**
     * Get a contextual attribute from a dependency.
     *
     * @param  \ReflectionParameter  $dependency
     * @return \ReflectionAttribute|null
     */
    public static function get_contextual_attribute_from_dependency($dependency)
    {
        return $dependency->get_attributes(Contextual_Attribute::class, Reflection_Attribute::IS_INSTANCEOF)[0] ?? null;
    }
}