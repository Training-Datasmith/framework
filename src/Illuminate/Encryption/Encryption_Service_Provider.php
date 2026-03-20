<?php

declare (strict_types=1);
namespace Illuminate\Encryption;

use Illuminate\Support\Service_Provider;
use Illuminate\Support\Str;
use Laravel\Serializable_Closure\Serializable_Closure;
class Encryption_Service_Provider extends Service_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->register_encrypter();
        $this->register_serializable_closure_security_key();
    }
    /**
     * Register the encrypter.
     *
     * @return void
     */
    protected function register_encrypter()
    {
        $this->app->singleton('encrypter', function ($app): \Illuminate\Encryption\Encrypter {
            $config = $app->make('config')->get('app');
            return (new Encrypter($this->parse_key($config), $config['cipher']))->previous_keys(array_map(fn($key) => $this->parse_key(['key' => $key]), $config['previous_keys'] ?? []));
        });
    }
    /**
     * Configure Serializable Closure signing for security.
     *
     * @return void
     */
    protected function register_serializable_closure_security_key()
    {
        $config = $this->app->make('config')->get('app');
        if (!class_exists(Serializable_Closure::class) || empty($config['key'])) {
            return;
        }
        Serializable_Closure::set_secret_key($this->parse_key($config));
    }
    /**
     * Parse the encryption key.
     *
     * @return string
     */
    protected function parse_key(array $config)
    {
        if (Str::starts_with($key = $this->key($config), $prefix = 'base64:')) {
            return base64_decode(Str::after($key, $prefix));
        }
        return $key;
    }
    /**
     * Extract the encryption key from the given configuration.
     *
     * @return string
     * @throws \Illuminate\Encryption\MissingAppKeyException
     */
    protected function key(array $config)
    {
        return tap($config['key'], function ($key): void {
            if (empty($key)) {
                throw new Missing_App_Key_Exception();
            }
        });
    }
}