<?php

declare (strict_types=1);
namespace Illuminate\Hashing;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Manager;
/**
 * @mixin \Illuminate\Contracts\Hashing\Hasher
 */
class Hash_Manager extends Manager implements Hasher
{
    /**
     * Create an instance of the Bcrypt hash Driver.
     */
    public function create_bcrypt_driver(): \Illuminate\Hashing\Bcrypt_Hasher
    {
        return new Bcrypt_Hasher($this->config->get('hashing.bcrypt') ?? []);
    }
    /**
     * Create an instance of the Argon2i hash Driver.
     */
    public function create_argon_driver(): \Illuminate\Hashing\Argon_Hasher
    {
        return new Argon_Hasher($this->config->get('hashing.argon') ?? []);
    }
    /**
     * Create an instance of the Argon2id hash Driver.
     */
    public function create_argon2id_driver(): \Illuminate\Hashing\Argon2id_Hasher
    {
        return new Argon2id_Hasher($this->config->get('hashing.argon') ?? []);
    }
    /**
     * Get information about the given hashed value.
     *
     * @param  string  $hashedValue
     * @return array
     */
    public function info($hashed_value)
    {
        return $this->driver()->info($hashed_value);
    }
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
    )
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
    public function check(
        #[\Sensitive_Parameter]
        $value,
        $hashed_value,
        array $options = []
    )
    {
        return $this->driver()->check($value, $hashed_value, $options);
    }
    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     * @return bool
     */
    public function needs_rehash($hashed_value, array $options = [])
    {
        return $this->driver()->needs_rehash($hashed_value, $options);
    }
    /**
     * Determine if a given string is already hashed.
     *
     * @param  string  $value
     */
    public function is_hashed(
        #[\Sensitive_Parameter]
        $value
    ): bool
    {
        return $this->driver()->info($value)['algo'] !== null;
    }
    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function get_default_driver()
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
    public function verify_configuration($value)
    {
        if (method_exists($driver = $this->driver(), 'verifyConfiguration')) {
            return $driver->verify_configuration($value);
        }
        return true;
    }
}