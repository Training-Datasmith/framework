<?php

declare(strict_types=1);

use Illuminate\Support\Traits\ReflectsClosures;

if (! function_exists('lazy')) {
    /**
     * Create a lazy instance.
     *
     * @template TValue of object
     *
     * @param  class-string<TValue>|(\Closure(TValue): mixed)  $class
     * @param  (\Closure(TValue): mixed)|int  $callback
     * @param  int  $options
     * @param  array<string, mixed>  $eager
     * @return TValue
     */
    function lazy($class, $callback = 0, $options = 0, $eager = []): object
    {
        static $closureReflector;

        $closureReflector ??= new class () {
            use ReflectsClosures;

            public function typeFromParameter(\Closure $callback)
            {
                return $this->firstClosureParameterType($callback);
            }
        };

        [$class, $callback, $options] = is_string($class)
            ? [$class, $callback, $options]
            : [$closureReflector->typeFromParameter($class), $class, $callback ?: $options];

        $reflectionClass = new ReflectionClass($class);

        $instance = $reflectionClass->newLazyGhost(function ($instance) use ($callback): void {
            $result = $callback($instance);

            if (is_array($result)) {
                $instance->__construct(...$result);
            }
        }, $options);

        foreach ($eager as $property => $value) {
            $reflectionClass->getProperty($property)->setRawValueWithoutLazyInitialization($instance, $value);
        }

        return $instance;
    }
}

if (! function_exists('proxy')) {
    /**
     * Create a lazy proxy instance.
     *
     * @template TValue of object
     *
     * @param  class-string<TValue>|(\Closure(TValue): TValue)  $class
     * @param  (\Closure(TValue): TValue)|int  $callback
     * @param  int  $options
     * @param  array<string, mixed>  $eager
     * @return TValue
     */
    function proxy($class, $callback = 0, $options = 0, $eager = []): object
    {
        static $closureReflector;

        $closureReflector = new class () {
            use ReflectsClosures;

            public function get(\Closure $callback)
            {
                return $this->closureReturnTypes($callback)[0] ?? $this->firstClosureParameterType($callback);
            }
        };

        [$class, $callback, $options] = is_string($class)
            ? [$class, $callback, $options]
            : [$closureReflector->get($class), $class, $callback ?: $options];

        $reflectionClass = new ReflectionClass($class);

        $proxy = $reflectionClass->newLazyProxy(function () use ($callback, $eager, &$proxy) {
            return $callback($proxy, $eager);
        }, $options);

        foreach ($eager as $property => $value) {
            $reflectionClass->getProperty($property)->setRawValueWithoutLazyInitialization($proxy, $value);
        }

        return $proxy;
    }
}
