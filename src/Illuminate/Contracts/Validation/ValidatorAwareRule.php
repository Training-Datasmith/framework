<?php

declare(strict_types=1);

namespace Illuminate\Contracts\Validation;

use Illuminate\Validation\Validator;

interface ValidatorAwareRule
{
    /**
     * Set the current validator.
     *
     * @return $this
     */
    public function setValidator(Validator $validator);
}
