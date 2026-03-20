<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Postgres_Connection;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
class Builder
{
    use Macroable;
    /**
     * The schema grammar instance.
     *
     * @var \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected $grammar;
    /**
     * The Blueprint resolver callback.
     *
     * @var \Closure(\Illuminate\Database\Connection, string, \Closure|null): \Illuminate\Database\Schema\Blueprint
     */
    protected $resolver;
    /**
     * The default string length for migrations.
     *
     * @var non-negative-int|null
     */
    public static $default_string_length = 255;
    /**
     * The default time precision for migrations.
     */
    public static ?int $default_time_precision = 0;
    /**
     * The default relationship morph key type.
     *
     * @var 'int'|'uuid'|'ulid'
     */
    public static $default_morph_key_type = 'int';
    /**
     * Create a new database Schema manager.
     */
    public function __construct(
        /**
         * The database connection instance.
         */
        protected \Illuminate\Database\Connection $connection
    )
    {
        $this->grammar = $this->connection->get_schema_grammar();
    }
    /**
     * Set the default string length for migrations.
     *
     * @param  non-negative-int  $length
     */
    public static function default_string_length($length): void
    {
        static::$default_string_length = $length;
    }
    /**
     * Set the default time precision for migrations.
     */
    public static function default_time_precision(?int $precision): void
    {
        static::$default_time_precision = $precision;
    }
    /**
     * Set the default morph key type for migrations.
     *
     *
     * @throws \InvalidArgumentException
     */
    public static function default_morph_key_type(string $type): void
    {
        if (!in_array($type, ['int', 'uuid', 'ulid'])) {
            throw new InvalidArgumentException("Morph key type must be 'int', 'uuid', or 'ulid'.");
        }
        static::$default_morph_key_type = $type;
    }
    /**
     * Set the default morph key type for migrations to UUIDs.
     */
    public static function morph_using_uuids(): void
    {
        static::default_morph_key_type('uuid');
    }
    /**
     * Set the default morph key type for migrations to ULIDs.
     */
    public static function morph_using_ulids(): void
    {
        static::default_morph_key_type('ulid');
    }
    /**
     * Create a database in the schema.
     *
     * @param  string  $name
     * @return bool
     */
    public function create_database($name)
    {
        return $this->connection->statement($this->grammar->compile_create_database($name));
    }
    /**
     * Drop a database from the schema if the database exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function drop_database_if_exists($name)
    {
        return $this->connection->statement($this->grammar->compile_drop_database_if_exists($name));
    }
    /**
     * Get the schemas that belong to the connection.
     *
     * @return list<array{name: string, path: string|null, default: bool}>
     */
    public function get_schemas(): array
    {
        return $this->connection->get_post_processor()->process_schemas($this->connection->select_from_write_connection($this->grammar->compile_schemas()));
    }
    /**
     * Determine if the given table exists.
     *
     * @param  string  $table
     * @return bool
     */
    public function has_table($table)
    {
        [$schema, $table] = $this->parse_schema_and_table($table);
        $table = $this->connection->get_table_prefix() . $table;
        if ($sql = $this->grammar->compile_table_exists($schema, $table)) {
            return (bool) $this->connection->scalar($sql);
        }
        foreach ($this->get_tables($schema ?? $this->get_current_schema_name()) as $value) {
            if (strtolower($table) === strtolower($value['name'])) {
                return true;
            }
        }
        return false;
    }
    /**
     * Determine if the given view exists.
     *
     * @param  string  $view
     */
    public function has_view($view): bool
    {
        [$schema, $view] = $this->parse_schema_and_table($view);
        $view = $this->connection->get_table_prefix() . $view;
        foreach ($this->get_views($schema ?? $this->get_current_schema_name()) as $value) {
            if (strtolower($view) === strtolower($value['name'])) {
                return true;
            }
        }
        return false;
    }
    /**
     * Get the tables that belong to the connection.
     *
     * @param  string|string[]|null  $schema
     * @return list<array{name: string, schema: string|null, schema_qualified_name: string, size: int|null, comment: string|null, collation: string|null, engine: string|null}>
     */
    public function get_tables($schema = null): array
    {
        return $this->connection->get_post_processor()->process_tables($this->connection->select_from_write_connection($this->grammar->compile_tables($schema)));
    }
    /**
     * Get the names of the tables that belong to the connection.
     *
     * @param  string|string[]|null  $schema
     * @param  bool  $schemaQualified
     * @return list<string>
     */
    public function get_table_listing($schema = null, $schema_qualified = true): array
    {
        return array_column($this->get_tables($schema), $schema_qualified ? 'schema_qualified_name' : 'name');
    }
    /**
     * Get the views that belong to the connection.
     *
     * @param  string|string[]|null  $schema
     * @return list<array{name: string, schema: string|null, schema_qualified_name: string, definition: string}>
     */
    public function get_views($schema = null): array
    {
        return $this->connection->get_post_processor()->process_views($this->connection->select_from_write_connection($this->grammar->compile_views($schema)));
    }
    /**
     * Get the user-defined types that belong to the connection.
     *
     * @param  string|string[]|null  $schema
     * @return list<array{name: string, schema: string, type: string, type: string, category: string, implicit: bool}>
     */
    public function get_types($schema = null)
    {
        return $this->connection->get_post_processor()->process_types($this->connection->select_from_write_connection($this->grammar->compile_types($schema)));
    }
    /**
     * Determine if the given table has a given column.
     *
     * @param  string  $table
     * @param  string  $column
     */
    public function has_column($table, $column): bool
    {
        return in_array(strtolower($column), array_map(strtolower(...), $this->get_column_listing($table)));
    }
    /**
     * Determine if the given table has given columns.
     *
     * @param  string  $table
     * @param  array<string>  $columns
     */
    public function has_columns($table, array $columns): bool
    {
        $table_columns = array_map(strtolower(...), $this->get_column_listing($table));
        foreach ($columns as $column) {
            if (!in_array(strtolower($column), $table_columns)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Execute a table builder callback if the given table has a given column.
     */
    public function when_table_has_column(string $table, string $column, Closure $callback): void
    {
        if ($this->has_column($table, $column)) {
            $this->table($table, fn(Blueprint $table) => $callback($table));
        }
    }
    /**
     * Execute a table builder callback if the given table doesn't have a given column.
     */
    public function when_table_doesnt_have_column(string $table, string $column, Closure $callback): void
    {
        if (!$this->has_column($table, $column)) {
            $this->table($table, fn(Blueprint $table) => $callback($table));
        }
    }
    /**
     * Execute a table builder callback if the given table has a given index.
     */
    public function when_table_has_index(string $table, string|array $index, Closure $callback, ?string $type = null): void
    {
        if ($this->has_index($table, $index, $type)) {
            $this->table($table, fn(Blueprint $table) => $callback($table));
        }
    }
    /**
     * Execute a table builder callback if the given table doesn't have a given index.
     */
    public function when_table_doesnt_have_index(string $table, string|array $index, Closure $callback, ?string $type = null): void
    {
        if (!$this->has_index($table, $index, $type)) {
            $this->table($table, fn(Blueprint $table) => $callback($table));
        }
    }
    /**
     * Get the data type for the given column name.
     *
     * @param  string  $table
     * @param  string  $column
     * @param  bool  $fullDefinition
     * @return string
     */
    public function get_column_type($table, $column, $full_definition = false)
    {
        $columns = $this->get_columns($table);
        foreach ($columns as $value) {
            if (strtolower($value['name']) === strtolower($column)) {
                return $full_definition ? $value['type'] : $value['type_name'];
            }
        }
        throw new InvalidArgumentException("There is no column with name '{$column}' on table '{$table}'.");
    }
    /**
     * Get the column listing for a given table.
     *
     * @param  string  $table
     * @return list<string>
     */
    public function get_column_listing($table): array
    {
        return array_column($this->get_columns($table), 'name');
    }
    /**
     * Get the columns for a given table.
     *
     * @param  string  $table
     * @return list<array{name: string, type: string, type_name: string, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null}>
     */
    public function get_columns($table)
    {
        [$schema, $table] = $this->parse_schema_and_table($table);
        $table = $this->connection->get_table_prefix() . $table;
        return $this->connection->get_post_processor()->process_columns($this->connection->select_from_write_connection($this->grammar->compile_columns($schema, $table)));
    }
    /**
     * Get the indexes for a given table.
     *
     * @param  string  $table
     * @return list<array{name: string, columns: list<string>, type: string, unique: bool, primary: bool}>
     */
    public function get_indexes($table)
    {
        [$schema, $table] = $this->parse_schema_and_table($table);
        $table = $this->connection->get_table_prefix() . $table;
        return $this->connection->get_post_processor()->process_indexes($this->connection->select_from_write_connection($this->grammar->compile_indexes($schema, $table)));
    }
    /**
     * Get the names of the indexes for a given table.
     *
     * @param  string  $table
     * @return list<string>
     */
    public function get_index_listing($table): array
    {
        return array_column($this->get_indexes($table), 'name');
    }
    /**
     * Determine if the given table has a given index.
     *
     * @param  string  $table
     * @param  string|array  $index
     * @param  string|null  $type
     */
    public function has_index($table, $index, $type = null): bool
    {
        $type = is_null($type) ? $type : strtolower($type);
        foreach ($this->get_indexes($table) as $value) {
            $type_matches = is_null($type) || $type === 'primary' && $value['primary'] || $type === 'unique' && $value['unique'] || $type === $value['type'];
            if (($value['name'] === $index || $value['columns'] === $index) && $type_matches) {
                return true;
            }
        }
        return false;
    }
    /**
     * Get the foreign keys for a given table.
     *
     * @param  string  $table
     * @return array
     */
    public function get_foreign_keys($table)
    {
        [$schema, $table] = $this->parse_schema_and_table($table);
        $table = $this->connection->get_table_prefix() . $table;
        return $this->connection->get_post_processor()->process_foreign_keys($this->connection->select_from_write_connection($this->grammar->compile_foreign_keys($schema, $table)));
    }
    /**
     * Modify a table on the schema.
     *
     * @param  string  $table
     */
    public function table($table, Closure $callback): void
    {
        $this->build($this->create_blueprint($table, $callback));
    }
    /**
     * Create a new table on the schema.
     *
     * @param  string  $table
     */
    public function create($table, Closure $callback): void
    {
        $this->build(tap($this->create_blueprint($table), function ($blueprint) use ($callback): void {
            $blueprint->create();
            $callback($blueprint);
        }));
    }
    /**
     * Drop a table from the schema.
     *
     * @param  string  $table
     */
    public function drop($table): void
    {
        $this->build(tap($this->create_blueprint($table), function ($blueprint): void {
            $blueprint->drop();
        }));
    }
    /**
     * Drop a table from the schema if it exists.
     *
     * @param  string  $table
     */
    public function drop_if_exists($table): void
    {
        $this->build(tap($this->create_blueprint($table), function ($blueprint): void {
            $blueprint->drop_if_exists();
        }));
    }
    /**
     * Drop columns from a table schema.
     *
     * @param  string  $table
     * @param  string|array<string>  $columns
     */
    public function drop_columns($table, $columns): void
    {
        $this->table($table, function (Blueprint $blueprint) use ($columns): void {
            $blueprint->drop_column($columns);
        });
    }
    /**
     * Drop all tables from the database.
     *
     *
     * @throws \LogicException
     */
    public function drop_all_tables(): never
    {
        throw new LogicException('This database driver does not support dropping all tables.');
    }
    /**
     * Drop all views from the database.
     *
     *
     * @throws \LogicException
     */
    public function drop_all_views(): never
    {
        throw new LogicException('This database driver does not support dropping all views.');
    }
    /**
     * Drop all types from the database.
     *
     *
     * @throws \LogicException
     */
    public function drop_all_types(): never
    {
        throw new LogicException('This database driver does not support dropping all types.');
    }
    /**
     * Rename a table on the schema.
     *
     * @param  string  $from
     * @param  string  $to
     */
    public function rename($from, $to): void
    {
        $this->build(tap($this->create_blueprint($from), function ($blueprint) use ($to): void {
            $blueprint->rename($to);
        }));
    }
    /**
     * Enable foreign key constraints.
     *
     * @return bool
     */
    public function enable_foreign_key_constraints()
    {
        return $this->connection->statement($this->grammar->compile_enable_foreign_key_constraints());
    }
    /**
     * Disable foreign key constraints.
     *
     * @return bool
     */
    public function disable_foreign_key_constraints()
    {
        return $this->connection->statement($this->grammar->compile_disable_foreign_key_constraints());
    }
    /**
     * Disable foreign key constraints during the execution of a callback.
     *
     * @template TReturn
     *
     * @param  (\Closure(): TReturn)  $callback
     * @return TReturn
     */
    public function without_foreign_key_constraints(Closure $callback)
    {
        $this->disable_foreign_key_constraints();
        try {
            return $callback();
        } finally {
            $this->enable_foreign_key_constraints();
        }
    }
    /**
     * Create the vector extension on the schema if it does not exist.
     *
     * @param  string|null  $schema
     */
    public function ensure_vector_extension_exists($schema = null): void
    {
        $this->ensure_extension_exists('vector', $schema);
    }
    /**
     * Create a new extension on the schema if it does not exist.
     *
     * @param  string  $name
     * @param  string|null  $schema
     */
    public function ensure_extension_exists($name, $schema = null): void
    {
        if (!$this->get_connection() instanceof Postgres_Connection) {
            throw new RuntimeException('Extensions are only supported by Postgres.');
        }
        $name = $this->get_connection()->get_schema_grammar()->wrap($name);
        $this->get_connection()->statement(match (filled($schema)) {
            true => "create extension if not exists {$name} schema {$this->get_connection()->get_schema_grammar()->wrap($schema)}",
            false => "create extension if not exists {$name}",
        });
    }
    /**
     * Execute the blueprint to build / modify the table.
     *
     * @return void
     */
    protected function build(Blueprint $blueprint)
    {
        $blueprint->build();
    }
    /**
     * Create a new command set with a Closure.
     *
     * @param  string  $table
     * @return \Illuminate\Database\Schema\Blueprint
     */
    protected function create_blueprint($table, ?Closure $callback = null)
    {
        $connection = $this->connection;
        if (isset($this->resolver)) {
            return call_user_func($this->resolver, $connection, $table, $callback);
        }
        return Container::get_instance()->make(Blueprint::class, compact('connection', 'table', 'callback'));
    }
    /**
     * Get the names of the current schemas for the connection.
     *
     * @return string[]|null
     */
    public function get_current_schema_listing(): null
    {
        return null;
    }
    /**
     * Get the default schema name for the connection.
     *
     * @return string|null
     */
    public function get_current_schema_name()
    {
        return $this->get_current_schema_listing()[0] ?? null;
    }
    /**
     * Parse the given database object reference and extract the schema and table.
     *
     * @param  string  $reference
     * @param  string|bool|null  $withDefaultSchema
     * @return array{string|null, string}
     */
    public function parse_schema_and_table($reference, $with_default_schema = null): array
    {
        $segments = explode('.', $reference);
        if (count($segments) > 2) {
            throw new InvalidArgumentException("Using three-part references is not supported, you may use `Schema::connection('{$segments[0]}')` instead.");
        }
        $table = $segments[1] ?? $segments[0];
        $schema = match (true) {
            isset($segments[1]) => $segments[0],
            is_string($with_default_schema) => $with_default_schema,
            $with_default_schema => $this->get_current_schema_name(),
            default => null,
        };
        return [$schema, $table];
    }
    /**
     * Get the database connection instance.
     */
    public function get_connection(): \Illuminate\Database\Connection
    {
        return $this->connection;
    }
    /**
     * Set the Schema Blueprint resolver callback.
     *
     * @param  \Closure(\Illuminate\Database\Connection, string, \Closure|null): \Illuminate\Database\Schema\Blueprint  $resolver
     */
    public function blueprint_resolver(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }
}