<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Database\Eloquent;

use Illuminate\Database\Eloquent\Model;
interface Compares_Castable_Attributes
{
    /**
     * Determine if the given values are equal.
     *
     * @return bool
     */
    public function compare(Model $model, string $key, mixed $first_value, mixed $second_value);
}