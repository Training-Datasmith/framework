<?php

declare (strict_types=1);
namespace Illuminate\Database\Connectors;

use Exception;
use Illuminate\Database\Detects_Lost_Connections;
use PDO;
use Throwable;
class Connector
{
    use Detects_Lost_Connections;
    /**
     * The default PDO connection options.
     *
     * @var array
     */
    protected $options = [PDO::ATTR_CASE => PDO::CASE_NATURAL, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL, PDO::ATTR_STRINGIFY_FETCHES => false, PDO::ATTR_EMULATE_PREPARES => false];
    /**
     * Create a new PDO connection.
     *
     * @param  string  $dsn
     * @return \PDO
     *
     * @throws \Exception
     */
    public function create_connection($dsn, array $config, array $options)
    {
        [$username, $password] = [$config['username'] ?? null, $config['password'] ?? null];
        try {
            return $this->create_pdo_connection($dsn, $username, $password, $options);
        } catch (Exception $e) {
            return $this->try_again_if_caused_by_lost_connection($e, $dsn, $username, $password, $options);
        }
    }
    /**
     * Create a new PDO connection instance.
     *
     * @param  string  $dsn
     * @param  string  $username
     * @param  string  $password
     * @param  array  $options
     */
    protected function create_pdo_connection(
        $dsn,
        $username,
        #[\Sensitive_Parameter]
        $password,
        $options
    ): \PDO
    {
        return version_compare(PHP_VERSION, '8.4.0', '<') ? new PDO($dsn, $username, $password, $options) : PDO::connect($dsn, $username, $password, $options);
        /** @phpstan-ignore staticMethod.notFound (PHP 8.4) */
    }
    /**
     * Handle an exception that occurred during connect execution.
     *
     * @param  string  $dsn
     * @param  string  $username
     * @param  string  $password
     * @param  array  $options
     * @throws \Throwable
     */
    protected function try_again_if_caused_by_lost_connection(
        Throwable $e,
        $dsn,
        $username,
        #[\Sensitive_Parameter]
        $password,
        $options
    ): \PDO
    {
        if ($this->caused_by_lost_connection($e)) {
            return $this->create_pdo_connection($dsn, $username, $password, $options);
        }
        throw $e;
    }
    /**
     * Get the PDO options based on the configuration.
     *
     * @return array
     */
    public function get_options(array $config)
    {
        $options = $config['options'] ?? [];
        return array_diff_key($this->options, $options) + $options;
    }
    /**
     * Get the default PDO connection options.
     *
     * @return array
     */
    public function get_default_options()
    {
        return $this->options;
    }
    /**
     * Set the default PDO connection options.
     */
    public function set_default_options(array $options): void
    {
        $this->options = $options;
    }
}