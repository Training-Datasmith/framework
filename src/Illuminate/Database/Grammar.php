<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use RuntimeException;
abstract class Grammar
{
    use Macroable;
    /**
     * Create a new grammar instance.
     */
    public function __construct(
        /**
         * The connection used for escaping values.
         */
        protected \Illuminate\Database\Connection $connection
    )
    {
    }
    /**
     * Wrap an array of values.
     *
     * @param  array<\Illuminate\Contracts\Database\Query\Expression|string>  $values
     * @return array<string>
     */
    public function wrap_array(array $values)
    {
        return array_map($this->wrap(...), $values);
    }
    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $table
     * @param  string|null  $prefix
     * @return string
     */
    public function wrap_table($table, $prefix = null)
    {
        if ($this->is_expression($table)) {
            return $this->get_value($table);
        }
        $prefix ??= $this->connection->get_table_prefix();
        // If the table being wrapped has an alias we'll need to separate the pieces
        // so we can prefix the table and then wrap each of the segments on their
        // own and then join these both back together using the "as" connector.
        if (stripos($table, ' as ') !== false) {
            return $this->wrap_aliased_table($table, $prefix);
        }
        // If the table being wrapped has a custom schema name specified, we need to
        // prefix the last segment as the table name then wrap each segment alone
        // and eventually join them both back together using the dot connector.
        if (str_contains($table, '.')) {
            $table = substr_replace($table, '.' . $prefix, strrpos($table, '.'), 1);
            return (new Collection(explode('.', $table)))->map($this->wrap_value(...))->implode('.');
        }
        return $this->wrap_value($prefix . $table);
    }
    /**
     * Wrap a value in keyword identifiers.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $value
     * @return string
     */
    public function wrap($value)
    {
        if ($this->is_expression($value)) {
            return $this->get_value($value);
        }
        // If the value being wrapped has a column alias we will need to separate out
        // the pieces so we can wrap each of the segments of the expression on its
        // own, and then join these both back together using the "as" connector.
        if (stripos($value, ' as ') !== false) {
            return $this->wrap_aliased_value($value);
        }
        // If the given value is a JSON selector we will wrap it differently than a
        // traditional value. We will need to split this path and wrap each part
        // wrapped, etc. Otherwise, we will simply wrap the value as a string.
        if ($this->is_json_selector($value)) {
            return $this->wrap_json_selector($value);
        }
        return $this->wrap_segments(explode('.', $value));
    }
    /**
     * Wrap a value that has an alias.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrap_aliased_value($value)
    {
        $segments = preg_split('/\s+as\s+/i', $value);
        return $this->wrap($segments[0]) . ' as ' . $this->wrap_value($segments[1]);
    }
    /**
     * Wrap a table that has an alias.
     *
     * @param  string  $value
     * @param  string|null  $prefix
     * @return string
     */
    protected function wrap_aliased_table($value, $prefix = null)
    {
        $segments = preg_split('/\s+as\s+/i', $value);
        $prefix ??= $this->connection->get_table_prefix();
        return $this->wrap_table($segments[0], $prefix) . ' as ' . $this->wrap_value($prefix . $segments[1]);
    }
    /**
     * Wrap the given value segments.
     *
     * @param  list<string>  $segments
     * @return string
     */
    protected function wrap_segments($segments)
    {
        return (new Collection($segments))->map(fn($segment, $key) => $key == 0 && count($segments) > 1 ? $this->wrap_table($segment) : $this->wrap_value($segment))->implode('.');
    }
    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrap_value($value)
    {
        if ($value !== '*') {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function wrap_json_selector($value)
    {
        throw new RuntimeException('This database engine does not support JSON operations.');
    }
    /**
     * Determine if the given string is a JSON selector.
     *
     * @param  string  $value
     * @return bool
     */
    protected function is_json_selector($value)
    {
        return str_contains($value, '->');
    }
    /**
     * Convert an array of column names into a delimited string.
     *
     * @param  array<\Illuminate\Contracts\Database\Query\Expression|string>  $columns
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(', ', array_map($this->wrap(...), $columns));
    }
    /**
     * Create query parameter place-holders for an array.
     *
     * @param  array<mixed>  $values
     * @return string
     */
    public function parameterize(array $values)
    {
        return implode(', ', array_map($this->parameter(...), $values));
    }
    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param  mixed  $value
     * @return string
     */
    public function parameter($value)
    {
        return $this->is_expression($value) ? $this->get_value($value) : '?';
    }
    /**
     * Quote the given string literal.
     *
     * @param  string|array<string>  $value
     * @return string
     */
    public function quote_string($value)
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }
        return "'{$value}'";
    }
    /**
     * Escapes a value for safe SQL embedding.
     *
     * @param  string|float|int|bool|null  $value
     * @param  bool  $binary
     * @return string
     */
    public function escape($value, $binary = false)
    {
        return $this->connection->escape($value, $binary);
    }
    /**
     * Determine if the given value is a raw expression.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function is_expression($value)
    {
        return $value instanceof Expression;
    }
    /**
     * Transforms expressions to their scalar types.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string|int|float  $expression
     * @return string|int|float
     */
    public function get_value($expression)
    {
        if ($this->is_expression($expression)) {
            return $this->get_value($expression->get_value($this));
        }
        return $expression;
    }
    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function get_date_format()
    {
        return 'Y-m-d H:i:s';
    }
    /**
     * Get the grammar's table prefix.
     *
     * @deprecated Use DB::getTablePrefix()
     *
     * @return string
     */
    public function get_table_prefix()
    {
        return $this->connection->get_table_prefix();
    }
    /**
     * Set the grammar's table prefix.
     *
     * @deprecated Use DB::setTablePrefix()
     *
     * @param  string  $prefix
     * @return $this
     */
    public function set_table_prefix($prefix)
    {
        $this->connection->set_table_prefix($prefix);
        return $this;
    }
}