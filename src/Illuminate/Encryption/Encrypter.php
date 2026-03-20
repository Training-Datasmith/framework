<?php

declare (strict_types=1);
namespace Illuminate\Encryption;

use Illuminate\Contracts\Encryption\Decrypt_Exception;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Contracts\Encryption\Encrypt_Exception;
use Illuminate\Contracts\Encryption\String_Encrypter;
use RuntimeException;
class Encrypter implements Encrypter_Contract, String_Encrypter
{
    /**
     * The encryption key.
     *
     * @var string
     */
    protected $key;
    /**
     * The previous / legacy encryption keys.
     *
     * @var array
     */
    protected $previous_keys = [];
    /**
     * The algorithm used for encryption.
     *
     * @var string
     */
    protected $cipher;
    /**
     * The supported cipher algorithms and their properties.
     */
    private static array $supported_ciphers = ['aes-128-cbc' => ['size' => 16, 'aead' => false], 'aes-256-cbc' => ['size' => 32, 'aead' => false], 'aes-128-gcm' => ['size' => 16, 'aead' => true], 'aes-256-gcm' => ['size' => 32, 'aead' => true]];
    /**
     * Create a new encrypter instance.
     *
     * @param  string  $key
     * @param  string  $cipher
     *
     * @throws \RuntimeException
     */
    public function __construct($key, $cipher = 'aes-128-cbc')
    {
        $key = (string) $key;
        if (!static::supported($key, $cipher)) {
            $ciphers = implode(', ', array_keys(self::$supported_ciphers));
            throw new RuntimeException("Unsupported cipher or incorrect key length. Supported ciphers are: {$ciphers}.");
        }
        $this->key = $key;
        $this->cipher = $cipher;
    }
    /**
     * Determine if the given key and cipher combination is valid.
     *
     * @param  string  $key
     * @param  string  $cipher
     * @return bool
     */
    public static function supported($key, $cipher)
    {
        if (!isset(self::$supported_ciphers[strtolower($cipher)])) {
            return false;
        }
        return mb_strlen($key, '8bit') === self::$supported_ciphers[strtolower($cipher)]['size'];
    }
    /**
     * Create a new encryption key for the given cipher.
     *
     * @param  string  $cipher
     */
    public static function generate_key($cipher): string
    {
        return random_bytes(self::$supported_ciphers[strtolower($cipher)]['size'] ?? 32);
    }
    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     *
     * @throws \Illuminate\Contracts\Encryption\EncryptException
     */
    public function encrypt(
        #[\Sensitive_Parameter]
        $value,
        $serialize = true
    ): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(strtolower($this->cipher)));
        $value = \openssl_encrypt($serialize ? serialize($value) : $value, strtolower($this->cipher), $this->key, 0, $iv, $tag);
        if ($value === false) {
            throw new Encrypt_Exception('Could not encrypt the data.');
        }
        $iv = base64_encode($iv);
        $tag = base64_encode($tag ?? '');
        $mac = self::$supported_ciphers[strtolower($this->cipher)]['aead'] ? '' : $this->hash($iv, $value, $this->key);
        $json = json_encode(compact('iv', 'value', 'mac', 'tag'), JSON_UNESCAPED_SLASHES);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Encrypt_Exception('Could not encrypt the data.');
        }
        return base64_encode($json);
    }
    /**
     * Encrypt a string without serialization.
     *
     * @param  string  $value
     *
     * @throws \Illuminate\Contracts\Encryption\EncryptException
     */
    public function encrypt_string(
        #[\Sensitive_Parameter]
        $value
    ): string
    {
        return $this->encrypt($value, false);
    }
    /**
     * Decrypt the given value.
     *
     * @param  string  $payload
     * @param  bool  $unserialize
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decrypt($payload, $unserialize = true)
    {
        $payload = $this->get_json_payload($payload);
        $iv = base64_decode((string) $payload['iv']);
        $this->ensure_tag_is_valid($tag = empty($payload['tag']) ? null : base64_decode((string) $payload['tag']));
        $found_valid_mac = false;
        // Here we will decrypt the value. If we are able to successfully decrypt it
        // we will then unserialize it and return it out to the caller. If we are
        // unable to decrypt this value we will throw out an exception message.
        foreach ($this->get_all_keys() as $key) {
            if ($this->should_validate_mac() && !$found_valid_mac = $found_valid_mac || $this->valid_mac_for_key($payload, $key)) {
                continue;
            }
            $decrypted = \openssl_decrypt($payload['value'], strtolower($this->cipher), $key, 0, $iv, $tag ?? '');
            if ($decrypted !== false) {
                break;
            }
        }
        if ($this->should_validate_mac() && !$found_valid_mac) {
            throw new Decrypt_Exception('The MAC is invalid.');
        }
        if (($decrypted ?? false) === false) {
            throw new Decrypt_Exception('Could not decrypt the data.');
        }
        return $unserialize ? unserialize($decrypted) : $decrypted;
    }
    /**
     * Decrypt the given string without unserialization.
     *
     * @param  string  $payload
     * @return string
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decrypt_string($payload)
    {
        return $this->decrypt($payload, false);
    }
    /**
     * Create a MAC for the given value.
     *
     * @param  mixed  $value
     * @param  string  $key
     */
    protected function hash(
        #[\Sensitive_Parameter]
        string $iv,
        #[\Sensitive_Parameter]
        string $value,
        #[\Sensitive_Parameter]
        $key
    ): string
    {
        return hash_hmac('sha256', $iv . $value, $key);
    }
    /**
     * Get the JSON array from the given payload.
     *
     * @param  string  $payload
     * @return array
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    protected function get_json_payload($payload)
    {
        if (!is_string($payload)) {
            throw new Decrypt_Exception('The payload is invalid.');
        }
        $payload = json_decode(base64_decode($payload), true);
        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if (!$this->valid_payload($payload)) {
            throw new Decrypt_Exception('The payload is invalid.');
        }
        return $payload;
    }
    /**
     * Verify that the encryption payload is valid.
     *
     * @param  mixed  $payload
     * @return bool
     */
    protected function valid_payload($payload)
    {
        if (!is_array($payload)) {
            return false;
        }
        foreach (['iv', 'value', 'mac'] as $item) {
            if (!isset($payload[$item]) || !is_string($payload[$item])) {
                return false;
            }
        }
        if (isset($payload['tag']) && !is_string($payload['tag'])) {
            return false;
        }
        return strlen(base64_decode((string) $payload['iv'], true)) === openssl_cipher_iv_length(strtolower($this->cipher));
    }
    /**
     * Determine if the MAC for the given payload is valid for the primary key.
     */
    protected function valid_mac(array $payload): bool
    {
        return $this->valid_mac_for_key($payload, $this->key);
    }
    /**
     * Determine if the MAC is valid for the given payload and key.
     *
     * @param  string  $key
     */
    protected function valid_mac_for_key(
        #[\Sensitive_Parameter]
        array $payload,
        $key
    ): bool
    {
        return hash_equals($this->hash($payload['iv'], $payload['value'], $key), $payload['mac']);
    }
    /**
     * Ensure the given tag is a valid tag given the selected cipher.
     *
     * @param  string  $tag
     * @return void
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    protected function ensure_tag_is_valid($tag)
    {
        if (self::$supported_ciphers[strtolower($this->cipher)]['aead'] && strlen($tag) !== 16) {
            throw new Decrypt_Exception('Could not decrypt the data.');
        }
        if (!self::$supported_ciphers[strtolower($this->cipher)]['aead'] && is_string($tag)) {
            throw new Decrypt_Exception('Unable to use tag because the cipher algorithm does not support AEAD.');
        }
    }
    /**
     * Determine if we should validate the MAC while decrypting.
     */
    protected function should_validate_mac(): bool
    {
        return !self::$supported_ciphers[strtolower($this->cipher)]['aead'];
    }
    /**
     * Determine if the given value appears to be encrypted by this encrypter.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function appears_encrypted($value)
    {
        if (!is_string($value)) {
            return false;
        }
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        $payload = json_decode($decoded, true);
        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
    /**
     * Get the encryption key that the encrypter is currently using.
     *
     * @return string
     */
    public function get_key()
    {
        return $this->key;
    }
    /**
     * Get the current encryption key and all previous encryption keys.
     */
    public function get_all_keys(): array
    {
        return [$this->key, ...$this->previous_keys];
    }
    /**
     * Get the previous encryption keys.
     *
     * @return array
     */
    public function get_previous_keys()
    {
        return $this->previous_keys;
    }
    /**
     * Set the previous / legacy encryption keys that should be utilized if decryption fails.
     *
     * @return $this
     * @throws \RuntimeException
     */
    public function previous_keys(array $keys): static
    {
        foreach ($keys as $key) {
            if (!static::supported($key, $this->cipher)) {
                $ciphers = implode(', ', array_keys(self::$supported_ciphers));
                throw new RuntimeException("Unsupported cipher or incorrect key length. Supported ciphers are: {$ciphers}.");
            }
        }
        $this->previous_keys = $keys;
        return $this;
    }
}