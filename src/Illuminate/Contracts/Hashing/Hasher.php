<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Hashing;

interface Hasher
{
    /**
     * Get information about the given hashed value.
     *
     * @param  string  $hashedValue
     * @return array
     */
    public function info($hashed_value);
    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @return string
     */
    public function make(
        #[\Sensitive_Parameter]
        $value,
        array $options = []
    );
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
    );
    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     * @return bool
     */
    public function needs_rehash($hashed_value, array $options = []);
}