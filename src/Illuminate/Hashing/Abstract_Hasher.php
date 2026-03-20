<?php

declare (strict_types=1);
namespace Illuminate\Hashing;

abstract class Abstract_Hasher
{
    /**
     * Get information about the given hashed value.
     *
     * @param  string  $hashedValue
     * @return array
     */
    public function info($hashed_value)
    {
        return password_get_info($hashed_value);
    }
    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @return bool
     */
    public function check(
        #[\Sensitive_Parameter]
        $value,
        $hashed_value,
        array $options = []
    )
    {
        if (is_null($hashed_value) || strlen($hashed_value) === 0) {
            return false;
        }
        return password_verify($value, $hashed_value);
    }
}