<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json_Api\Exceptions;

use RuntimeException;
class Resource_Identification_Exception extends RuntimeException
{
    /**
     * Create an exception indicating we were unable to determine the resource ID for the given resource.
     *
     * @param  mixed  $resource
     */
    public static function attempting_to_determine_id_for($resource): self
    {
        $resource_type = get_debug_type($resource);
        return new self(sprintf('Unable to resolve resource object ID for [%s].', $resource_type));
    }
    /**
     * Create an exception indicating we were unable to determine the resource type for the given resource.
     *
     * @param  mixed  $resource
     */
    public static function attempting_to_determine_type_for($resource): self
    {
        $resource_type = get_debug_type($resource);
        return new self(sprintf('Unable to resolve resource object type for [%s].', $resource_type));
    }
}