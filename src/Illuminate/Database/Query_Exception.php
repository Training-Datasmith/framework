<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Throwable;
class Query_Exception extends PDOException
{
    /**
     * Create a new query exception instance.
     *
     * @param  string  $connectionName
     * @param  string  $sql
     * @param  null|'read'|'write'  $readWriteType
     */
    public function __construct(
        /**
         * The database connection name.
         */
        public $connection_name,
        /**
         * The SQL for the query.
         */
        protected $sql,
        /**
         * The bindings for the query.
         */
        protected array $bindings,
        Throwable $previous,
        /**
         * The connection details for the query (host, port, database, etc.).
         */
        protected array $connection_details = [],
        /**
         * The PDO read / write type for the executed query.
         */
        public $read_write_type = null
    )
    {
        parent::__construct('', 0, $previous);
        $this->code = $previous->get_code();
        $this->message = $this->format_message($this->connection_name, $this->sql, $this->bindings, $previous);
        if ($previous instanceof PDOException) {
            $this->error_info = $previous->error_info;
        }
    }
    /**
     * Format the SQL error message.
     *
     * @param  string  $sql
     * @param  array  $bindings
     */
    protected function format_message(string $connection_name, $sql, $bindings, Throwable $previous): string
    {
        $details = $this->format_connection_details();
        return $previous->get_message() . ' (Connection: ' . $connection_name . $details . ', SQL: ' . Str::replace_array('?', $bindings, $sql) . ')';
    }
    /**
     * Format the connection details for the error message.
     */
    protected function format_connection_details(): string
    {
        if (empty($this->connection_details)) {
            return '';
        }
        $driver = $this->connection_details['driver'] ?? '';
        $segments = [];
        if ($driver !== 'sqlite') {
            if (!empty($this->connection_details['unix_socket'])) {
                $segments[] = 'Socket: ' . $this->connection_details['unix_socket'];
            } else {
                $host = $this->connection_details['host'] ?? '';
                $segments[] = 'Host: ' . (is_array($host) ? implode(', ', $host) : $host);
                $segments[] = 'Port: ' . ($this->connection_details['port'] ?? '');
            }
        }
        $segments[] = 'Database: ' . ($this->connection_details['database'] ?? '');
        return ', ' . implode(', ', $segments);
    }
    /**
     * Get the connection name for the query.
     *
     * @return string
     */
    public function get_connection_name()
    {
        return $this->connection_name;
    }
    /**
     * Get the SQL for the query.
     *
     * @return string
     */
    public function get_sql()
    {
        return $this->sql;
    }
    /**
     * Get the raw SQL representation of the query with embedded bindings.
     */
    public function get_raw_sql(): string
    {
        return DB::connection($this->get_connection_name())->get_query_grammar()->substitute_bindings_into_raw_sql($this->get_sql(), $this->get_bindings());
    }
    /**
     * Get the bindings for the query.
     */
    public function get_bindings(): array
    {
        return $this->bindings;
    }
    /**
     * Get information about the connection such as host, port, database, etc.
     */
    public function get_connection_details(): array
    {
        return $this->connection_details;
    }
}