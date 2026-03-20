<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Encryption;

interface Encrypter
{
    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     * @return string
     *
     * @throws \Illuminate\Contracts\Encryption\EncryptException
     */
    public function encrypt(
        #[\Sensitive_Parameter]
        $value,
        $serialize = true
    );
    /**
     * Decrypt the given value.
     *
     * @param  string  $payload
     * @param  bool  $unserialize
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decrypt($payload, $unserialize = true);
    /**
     * Get the encryption key that the encrypter is currently using.
     *
     * @return string
     */
    public function get_key();
    /**
     * Get the current encryption key and all previous encryption keys.
     *
     * @return array
     */
    public function get_all_keys();
    /**
     * Get the previous encryption keys.
     *
     * @return array
     */
    public function get_previous_keys();
}