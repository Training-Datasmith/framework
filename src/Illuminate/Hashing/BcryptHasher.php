<?php

declare (strict_types=1);
namespace Illuminate\Hashing;

use Error;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use InvalidArgumentException;
use RuntimeException;
class Bcrypt_Hasher extends Abstract_Hasher implements Hasher_Contract
{
    /**
     * The default cost factor.
     *
     * @var int
     */
    protected $rounds = 12;
    /**
     * Indicates whether to perform an algorithm check.
     *
     * @var bool
     */
    protected $verify_algorithm = false;
    /**
     * The maximum allowed length of strings that can be hashed.
     *
     * @var int|null
     */
    protected $limit;
    /**
     * Create a new hasher instance.
     */
    public function __construct(array $options = [])
    {
        $this->rounds = $options['rounds'] ?? $this->rounds;
        $this->verify_algorithm = $options['verify'] ?? $this->verify_algorithm;
        $this->limit = $options['limit'] ?? $this->limit;
    }
    /**
     * Hash the given value.
     *
     * @param  string  $value
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function make(
        #[\Sensitive_Parameter]
        $value,
        array $options = []
    ): string
    {
        try {
            if ($this->limit && strlen($value) > $this->limit) {
                throw new InvalidArgumentException('Value is too long to hash. Value must be less than ' . $this->limit . ' bytes.');
            }
            $hash = password_hash($value, PASSWORD_BCRYPT, ['cost' => $this->cost($options)]);
        } catch (Error) {
            throw new RuntimeException('Bcrypt hashing not supported.');
        }
        return $hash;
    }
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
            throw new RuntimeException('This password does not use the Bcrypt algorithm.');
        }
        return parent::check($value, $hashed_value, $options);
    }
    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     */
    public function needs_rehash($hashed_value, array $options = []): bool
    {
        return password_needs_rehash($hashed_value, PASSWORD_BCRYPT, ['cost' => $this->cost($options)]);
    }
    /**
     * Verifies that the configuration is less than or equal to what is configured.
     *
     * @internal
     */
    public function verify_configuration($value): bool
    {
        return $this->is_using_correct_algorithm($value) && $this->is_using_valid_options($value);
    }
    /**
     * Verify the hashed value's algorithm.
     *
     * @param  string  $hashedValue
     */
    protected function is_using_correct_algorithm($hashed_value): bool
    {
        return $this->info($hashed_value)['algoName'] === 'bcrypt';
    }
    /**
     * Verify the hashed value's options.
     *
     * @param  string  $hashedValue
     */
    protected function is_using_valid_options($hashed_value): bool
    {
        ['options' => $options] = $this->info($hashed_value);
        if (!is_int($options['cost'] ?? null)) {
            return false;
        }
        if ($options['cost'] > $this->rounds) {
            return false;
        }
        return true;
    }
    /**
     * Set the default password work factor.
     *
     * @param  int  $rounds
     * @return $this
     */
    public function set_rounds($rounds): static
    {
        $this->rounds = (int) $rounds;
        return $this;
    }
    /**
     * Extract the cost value from the options array.
     *
     * @return int
     */
    protected function cost(array $options = [])
    {
        return $options['rounds'] ?? $this->rounds;
    }
}