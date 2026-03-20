<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Database\Eloquent;

use Illuminate\Database\Eloquent\Model;
interface Casts_Inbound_Attributes
{
    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  array<string, mixed>  $attributes
     * @return mixed
     */
    public function set(Model $model, string $key, mixed $value, array $attributes);
}