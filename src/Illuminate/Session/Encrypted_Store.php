<?php

declare(strict_types=1);

namespace Illuminate\Session;

use Illuminate\Contracts\Encryption\DecryptException;
use SessionHandlerInterface;

class EncryptedStore extends Store
{
    /**
     * Create a new session instance.
     *
     * @param  string  $name
     * @param  string|null  $id
     * @param  string  $serialization
     */
    public function __construct($name, SessionHandlerInterface $handler, /**
     * The encrypter instance.
     */
        protected \Illuminate\Contracts\Encryption\Encrypter $encrypter, $id = null, $serialization = 'php')
    {
        parent::__construct($name, $handler, $id, $serialization);
    }

    /**
     * Prepare the raw string data from the session for unserialization.
     *
     * @param  string  $data
     * @return string
     */
    protected function prepareForUnserialize($data)
    {
        try {
            return $this->encrypter->decrypt($data);
        } catch (DecryptException) {
            return $this->serialization === 'json' ? json_encode([]) : serialize([]);
        }
    }

    /**
     * Prepare the serialized session data for storage.
     *
     * @param  string  $data
     * @return string
     */
    protected function prepareForStorage($data)
    {
        return $this->encrypter->encrypt($data);
    }

    /**
     * Get the encrypter instance.
     */
    public function getEncrypter(): \Illuminate\Contracts\Encryption\Encrypter
    {
        return $this->encrypter;
    }
}
