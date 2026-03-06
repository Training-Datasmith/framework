<?php

namespace Illuminate\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Throwable;

class QueryException extends PDOException
{
    /**
     * Create a new query exception instance.
     *
     * @param  string  $connectionName
     * @param  string  $sql
     * @param  null|'read'|'write'  $readWriteType
     */
    public function __construct(/**
     * The database connection name.
     */
    public $connectionName, /**
     * The SQL for the query.
     */
    protected $sql, /**
     * The bindings for the query.
     */
    protected array $bindings, Throwable $previous, /**
     * The connection details for the query (host, port, database, etc.).
     */
    protected array $connectionDetails = [], /**
     * The PDO read / write type for the executed query.
     */
    public $readWriteType = null)
    {
        parent::__construct('', 0, $previous);
        $this->code = $previous->getCode();
        $this->message = $this->formatMessage($this->connectionName, $this->sql, $this->bindings, $previous);

        if ($previous instanceof PDOException) {
            $this->errorInfo = $previous->errorInfo;
        }
    }

    /**
     * Format the SQL error message.
     *
     * @param  string  $sql
     * @param  array  $bindings
     */
    protected function formatMessage(string $connectionName, $sql, $bindings, Throwable $previous): string
    {
        $details = $this->formatConnectionDetails();

        return $previous->getMessage().' (Connection: '.$connectionName.$details.', SQL: '.Str::replaceArray('?', $bindings, $sql).')';
    }

    /**
     * Format the connection details for the error message.
     */
    protected function formatConnectionDetails(): string
    {
        if (empty($this->connectionDetails)) {
            return '';
        }

        $driver = $this->connectionDetails['driver'] ?? '';

        $segments = [];

        if ($driver !== 'sqlite') {
            if (! empty($this->connectionDetails['unix_socket'])) {
                $segments[] = 'Socket: '.$this->connectionDetails['unix_socket'];
            } else {
                $host = $this->connectionDetails['host'] ?? '';

                $segments[] = 'Host: '.(is_array($host) ? implode(', ', $host) : $host);
                $segments[] = 'Port: '.($this->connectionDetails['port'] ?? '');
            }
        }

        $segments[] = 'Database: '.($this->connectionDetails['database'] ?? '');

        return ', '.implode(', ', $segments);
    }

    /**
     * Get the connection name for the query.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * Get the SQL for the query.
     *
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Get the raw SQL representation of the query with embedded bindings.
     */
    public function getRawSql(): string
    {
        return DB::connection($this->getConnectionName())
            ->getQueryGrammar()
            ->substituteBindingsIntoRawSql($this->getSql(), $this->getBindings());
    }

    /**
     * Get the bindings for the query.
     *
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Get information about the connection such as host, port, database, etc.
     *
     * @return array
     */
    public function getConnectionDetails()
    {
        return $this->connectionDetails;
    }
}
