<?php

declare(strict_types=1);

namespace Illuminate\Routing;

trait FiltersControllerMiddleware
{
    /**
     * Determine if the given options exclude a particular method.
     *
     * @param  string  $method
     */
    public static function methodExcludedByOptions($method, array $options): bool
    {
        return (isset($options['only']) && ! in_array($method, (array) $options['only'])) ||
               (! empty($options['except']) && in_array($method, (array) $options['except']));
    }
}
