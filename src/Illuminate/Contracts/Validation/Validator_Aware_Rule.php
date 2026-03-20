<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Validation;

use Illuminate\Validation\Validator;
interface Validator_Aware_Rule
{
    /**
     * Set the current validator.
     *
     * @return $this
     */
    public function set_validator(Validator $validator);
}