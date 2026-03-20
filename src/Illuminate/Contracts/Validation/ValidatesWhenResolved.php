<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Validation;

interface Validates_When_Resolved
{
    /**
     * Validate the given class instance.
     *
     * @return void
     */
    public function validate_resolved();
}