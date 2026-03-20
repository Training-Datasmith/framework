<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Encryption;

interface String_Encrypter
{
    /**
     * Encrypt a string without serialization.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \Illuminate\Contracts\Encryption\EncryptException
     */
    public function encrypt_string(
        #[\Sensitive_Parameter]
        $value
    );
    /**
     * Decrypt the given string without unserialization.
     *
     * @param  string  $payload
     * @return string
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decrypt_string($payload);
}