<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Database\Eloquent;

interface Deviates_Castable_Attributes
{
    /**
     * Increment the attribute.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  mixed  $value
     * @return mixed
     */
    public function increment($model, string $key, $value, array $attributes);
    /**
     * Decrement the attribute.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  mixed  $value
     * @return mixed
     */
    public function decrement($model, string $key, $value, array $attributes);
}