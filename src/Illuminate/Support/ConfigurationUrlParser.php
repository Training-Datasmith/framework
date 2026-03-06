<?php

declare(strict_types=1);

namespace Illuminate\Support;

use InvalidArgumentException;

class ConfigurationUrlParser
{
    /**
     * The drivers aliases map.
     *
     * @var array
     */
    protected static $driverAliases = [
        'mssql' => 'sqlsrv',
        'mysql2' => 'mysql', // RDS
        'postgres' => 'pgsql',
        'postgresql' => 'pgsql',
        'sqlite3' => 'sqlite',
        'redis' => 'tcp',
        'rediss' => 'tls',
    ];

    /**
     * Parse the database configuration, hydrating options using a database configuration URL if possible.
     *
     * @param  array|string  $config
     * @return array
     */
    public function parseConfiguration($config)
    {
        if (is_string($config)) {
            $config = ['url' => $config];
        }

        $url = Arr::pull($config, 'url');

        if (! $url) {
            return $config;
        }

        $rawComponents = $this->parseUrl($url);

        $decodedComponents = $this->parseStringsToNativeTypes(
            array_map(rawurldecode(...), $rawComponents)
        );

        return array_merge(
            $config,
            $this->getPrimaryOptions($decodedComponents),
            $this->getQueryOptions($rawComponents)
        );
    }

    /**
     * Get the primary database connection options.
     */
    protected function getPrimaryOptions(array $url): array
    {
        return array_filter([
            'driver' => $this->getDriver($url),
            'database' => $this->getDatabase($url),
            'host' => $url['host'] ?? null,
            'port' => $url['port'] ?? null,
            'username' => $url['user'] ?? null,
            'password' => $url['pass'] ?? null,
        ], fn ($value): bool => ! is_null($value));
    }

    /**
     * Get the database driver from the URL.
     *
     * @return string|null
     */
    protected function getDriver(array $url)
    {
        $alias = $url['scheme'] ?? null;

        if (! $alias) {
            return;
        }

        return static::$driverAliases[$alias] ?? $alias;
    }

    /**
     * Get the database name from the URL.
     */
    protected function getDatabase(array $url): ?string
    {
        $path = $url['path'] ?? null;

        return $path && $path !== '/' ? substr((string) $path, 1) : null;
    }

    /**
     * Get all of the additional database options from the query string.
     *
     * @return array
     */
    protected function getQueryOptions(array $url)
    {
        $queryString = $url['query'] ?? null;

        if (! $queryString) {
            return [];
        }

        $query = [];

        parse_str((string) $queryString, $query);

        return $this->parseStringsToNativeTypes($query);
    }

    /**
     * Parse the string URL to an array of components.
     *
     * @param  string  $url
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function parseUrl($url): int|string|array|null
    {
        $url = preg_replace('#^(sqlite3?):///#', '$1://null/', $url);

        $parsedUrl = parse_url((string) $url);

        if ($parsedUrl === false) {
            throw new InvalidArgumentException('The database configuration URL is malformed.');
        }

        return $parsedUrl;
    }

    /**
     * Convert string casted values to their native types.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function parseStringsToNativeTypes($value)
    {
        if (is_array($value)) {
            return array_map($this->parseStringsToNativeTypes(...), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $parsedValue = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $parsedValue;
        }

        return $value;
    }

    /**
     * Get all of the current drivers' aliases.
     *
     * @return array
     */
    public static function getDriverAliases()
    {
        return static::$driverAliases;
    }

    /**
     * Add the given driver alias to the driver aliases array.
     *
     * @param  string  $alias
     * @param  string  $driver
     */
    public static function addDriverAlias($alias, $driver): void
    {
        static::$driverAliases[$alias] = $driver;
    }
}
