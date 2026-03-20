<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Validation;

interface Data_Aware_Rule
{
    /**
     * Set the data under validation.
     *
     * @return $this
     */
    public function set_data(array $data);
}