<?php

declare(strict_types=1);

namespace Illuminate\Routing;

use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionMethod;

class RouteSignatureParameters
{
    /**
     * Extract the route action's signature parameters.
     *
     * @return array
     */
    public static function fromAction(array $action, array $conditions = [])
    {
        $callback = RouteAction::containsSerializedClosure($action)
            ? unserialize($action['uses'])->getClosure()
            : $action['uses'];

        $parameters = is_string($callback)
            ? static::fromClassMethodString($callback)
            : (new ReflectionFunction($callback))->getParameters();

        return match (true) {
            ! empty($conditions['subClass']) => array_filter($parameters, fn ($p): bool => Reflector::isParameterSubclassOf($p, $conditions['subClass'])),
            ! empty($conditions['backedEnum']) => array_filter($parameters, Reflector::isParameterBackedEnumWithStringBackingType(...)),
            default => $parameters,
        };
    }

    /**
     * Get the parameters for the given class / method by string.
     *
     * @param  string  $uses
     */
    protected static function fromClassMethodString($uses): array
    {
        [$class, $method] = Str::parseCallback($uses);

        if (! method_exists($class, $method) && Reflector::isCallable($class, $method)) {
            return [];
        }

        return (new ReflectionMethod($class, $method))->getParameters();
    }
}
