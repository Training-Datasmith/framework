<?php

namespace Illuminate\Hashing;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Manager;

/**
 * @mixin \Illuminate\Contracts\Hashing\Hasher
 */
class HashManager extends Manager implements Hasher
{
    /**
     * Create an instance of the Bcrypt hash Driver.
     */
    public function createBcryptDriver(): \Illuminate\Hashing\BcryptHasher
    {
        return new BcryptHasher($this->config->get('hashing.bcrypt') ?? []);
    }

    /**
     * Create an instance of the Argon2i hash Driver.
     */
    public function createArgonDriver(): \Illuminate\Hashing\ArgonHasher
    {
        return new ArgonHasher($this->config->get('hashing.argon') ?? []);
    }

    /**
     * Create an instance of the Argon2id hash Driver.
     */
    public function createArgon2idDriver(): \Illuminate\Hashing\Argon2IdHasher
    {
        return new Argon2IdHasher($this->config->get('hashing.argon') ?? []);
    }

    /**
     * Get information about the given hashed value.
     *
     * @param  string  $hashedValue
     * @return array
     */
    public function info($hashedValue)
    {
        return $this->driver()->info($hashedValue);
    }

    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @return string
     */
    public function make(#[\SensitiveParameter] $value, array $options = [])
    {
        return $this->driver()->make($value, $options);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @return bool
     */
    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = [])
    {
        return $this->driver()->check($value, $hashedValue, $options);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     * @return bool
     */
    public function needsRehash($hashedValue, array $options = [])
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    /**
     * Determine if a given string is already hashed.
     *
     * @param  string  $value
     */
    public function isHashed(#[\SensitiveParameter] $value): bool
    {
        return $this->driver()->info($value)['algo'] !== null;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->config->get('hashing.driver', 'bcrypt');
    }

    /**
     * Verifies that the configuration is less than or equal to what is configured.
     *
     * @param  array  $value
     * @return bool
     *
     * @internal
     */
    public function verifyConfiguration($value)
    {
        if (method_exists($driver = $this->driver(), 'verifyConfiguration')) {
            return $driver->verifyConfiguration($value);
        }

        return true;
    }
}
