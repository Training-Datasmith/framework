<?php

declare(strict_types=1);

namespace Illuminate\Http\Resources\JsonApi\Exceptions;

use RuntimeException;

class ResourceIdentificationException extends RuntimeException
{
    /**
     * Create an exception indicating we were unable to determine the resource ID for the given resource.
     *
     * @param  mixed  $resource
     */
    public static function attemptingToDetermineIdFor($resource): self
    {
        $resourceType = get_debug_type($resource);

        return new self(sprintf(
            'Unable to resolve resource object ID for [%s].',
            $resourceType
        ));
    }

    /**
     * Create an exception indicating we were unable to determine the resource type for the given resource.
     *
     * @param  mixed  $resource
     */
    public static function attemptingToDetermineTypeFor($resource): self
    {
        $resourceType = get_debug_type($resource);

        return new self(sprintf(
            'Unable to resolve resource object type for [%s].',
            $resourceType
        ));
    }
}
