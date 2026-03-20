<?php

declare (strict_types=1);
namespace Illuminate\Hashing;

use RuntimeException;
class Argon2id_Hasher extends Argon_Hasher
{
    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @return bool
     * @throws \RuntimeException
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
        if ($this->verify_algorithm && !$this->is_using_correct_algorithm($hashed_value)) {
            throw new RuntimeException('This password does not use the Argon2id algorithm.');
        }
        return password_verify($value, $hashed_value);
    }
    /**
     * Verify the hashed value's algorithm.
     *
     * @param  string  $hashedValue
     */
    protected function is_using_correct_algorithm($hashed_value): bool
    {
        return $this->info($hashed_value)['algoName'] === 'argon2id';
    }
    /**
     * Get the algorithm that should be used for hashing.
     */
    protected function algorithm(): string
    {
        return PASSWORD_ARGON2ID;
    }
}