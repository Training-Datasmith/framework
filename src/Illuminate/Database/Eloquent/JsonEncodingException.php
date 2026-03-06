<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent;

use RuntimeException;

class JsonEncodingException extends RuntimeException
{
    /**
     * Create a new JSON encoding exception for the model.
     *
     * @param  mixed  $model
     */
    public static function forModel($model, string $message): static
    {
        return new static('Error encoding model ['.$model::class.'] with ID ['.$model->getKey().'] to JSON: '.$message);
    }

    /**
     * Create a new JSON encoding exception for the resource.
     *
     * @param  \Illuminate\Http\Resources\Json\JsonResource  $resource
     */
    public static function forResource($resource, string $message): static
    {
        $model = $resource->resource;

        return new static('Error encoding resource ['.$resource::class.'] with model ['.$model::class.'] with ID ['.$model->getKey().'] to JSON: '.$message);
    }

    /**
     * Create a new JSON encoding exception for an attribute.
     *
     * @param  mixed  $model
     * @param  mixed  $key
     * @param  string  $message
     */
    public static function forAttribute($model, $key, $message): static
    {
        $class = $model::class;

        return new static("Unable to encode attribute [{$key}] for model [{$class}] to JSON: {$message}.");
    }
}
