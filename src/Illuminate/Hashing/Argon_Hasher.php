<?php

declare (strict_types=1);
namespace Illuminate\Hashing;

use Error;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use RuntimeException;
class Argon_Hasher extends Abstract_Hasher implements Hasher_Contract
{
    /**
     * The default memory cost factor.
     *
     * @var int
     */
    protected $memory = 1024;
    /**
     * The default time cost factor.
     *
     * @var int
     */
    protected $time = 2;
    /**
     * The default threads factor.
     *
     * @var int
     */
    protected $threads = 2;
    /**
     * Indicates whether to perform an algorithm check.
     *
     * @var bool
     */
    protected $verify_algorithm = false;
    /**
     * Create a new hasher instance.
     */
    public function __construct(array $options = [])
    {
        $this->time = $options['time'] ?? $this->time;
        $this->memory = $options['memory'] ?? $this->memory;
        $this->threads = $this->threads($options);
        $this->verify_algorithm = $options['verify'] ?? $this->verify_algorithm;
    }
    /**
     * Hash the given value.
     *
     * @param  string  $value
     *
     * @throws \RuntimeException
     */
    public function make(
        #[\Sensitive_Parameter]
        $value,
        array $options = []
    ): string
    {
        try {
            $hash = password_hash($value, $this->algorithm(), ['memory_cost' => $this->memory($options), 'time_cost' => $this->time($options), 'threads' => $this->threads($options)]);
        } catch (Error) {
            throw new RuntimeException('Argon2 hashing not supported.');
        }
        return $hash;
    }
    /**
     * Get the algorithm that should be used for hashing.
     */
    protected function algorithm(): string
    {
        return PASSWORD_ARGON2I;
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
            throw new RuntimeException('This password does not use the Argon2i algorithm.');
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
        return password_needs_rehash($hashed_value, $this->algorithm(), ['memory_cost' => $this->memory($options), 'time_cost' => $this->time($options), 'threads' => $this->threads($options)]);
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
        return $this->info($hashed_value)['algoName'] === 'argon2i';
    }
    /**
     * Verify the hashed value's options.
     *
     * @param  string  $hashedValue
     */
    protected function is_using_valid_options($hashed_value): bool
    {
        ['options' => $options] = $this->info($hashed_value);
        if (!is_int($options['memory_cost'] ?? null) || !is_int($options['time_cost'] ?? null) || !is_int($options['threads'] ?? null)) {
            return false;
        }
        if ($options['memory_cost'] > $this->memory || $options['time_cost'] > $this->time || $options['threads'] > $this->threads) {
            return false;
        }
        return true;
    }
    /**
     * Set the default password memory factor.
     *
     * @return $this
     */
    public function set_memory(int $memory): static
    {
        $this->memory = $memory;
        return $this;
    }
    /**
     * Set the default password timing factor.
     *
     * @return $this
     */
    public function set_time(int $time): static
    {
        $this->time = $time;
        return $this;
    }
    /**
     * Set the default password threads factor.
     *
     * @return $this
     */
    public function set_threads(int $threads): static
    {
        $this->threads = $threads;
        return $this;
    }
    /**
     * Extract the memory cost value from the options array.
     *
     * @return int
     */
    protected function memory(array $options)
    {
        return $options['memory'] ?? $this->memory;
    }
    /**
     * Extract the time cost value from the options array.
     *
     * @return int
     */
    protected function time(array $options)
    {
        return $options['time'] ?? $this->time;
    }
    /**
     * Extract the thread's value from the options array.
     *
     * @return int
     */
    protected function threads(array $options)
    {
        if (defined('PASSWORD_ARGON2_PROVIDER') && PASSWORD_ARGON2_PROVIDER === 'sodium') {
            return 1;
        }
        return $options['threads'] ?? $this->threads;
    }
}