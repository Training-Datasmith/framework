<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Validation;

interface Uncompromised_Verifier
{
    /**
     * Verify that the given data has not been compromised in data leaks.
     *
     * @param  array  $data
     * @return bool
     */
    public function verify($data);
}