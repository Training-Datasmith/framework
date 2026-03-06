<?php

declare(strict_types=1);

namespace Illuminate\Database\Schema;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
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
    public static $defaultStringLength = 255;

    /**
     * The default time precision for migrations.
     */
    public static ?int $defaultTimePrecision = 0;

    /**
     * The default relationship morph key type.
     *
     * @var 'int'|'uuid'|'ulid'
     */
    public static $defaultMorphKeyType = 'int';

    /**
     * Create a new database Schema manager.
     */
    public function __construct(/**
     * The database connection instance.
     */
        protected \Illuminate\Database\Connection $connection
    ) {
        $this->grammar = $this->connection->getSchemaGrammar();
    }

    /**
     * Set the default string length for migrations.
     *
     * @param  non-negative-int  $length
     */
    public static function defaultStringLength($length): void
    {
        static::$defaultStringLength = $length;
    }

    /**
     * Set the default time precision for migrations.
     */
    public static function defaultTimePrecision(?int $precision): void
    {
        static::$defaultTimePrecision = $precision;
    }

    /**
     * Set the default morph key type for migrations.
     *
     *
     * @throws \InvalidArgumentException
     */
    public static function defaultMorphKeyType(string $type): void
    {
        if (! in_array($type, ['int', 'uuid', 'ulid'])) {
            throw new InvalidArgumentException("Morph key type must be 'int', 'uuid', or 'ulid'.");
        }

        static::$defaultMorphKeyType = $type;
    }

    /**
     * Set the default morph key type for migrations to UUIDs.
     */
    public static function morphUsingUuids(): void
    {
        static::defaultMorphKeyType('uuid');
    }

    /**
     * Set the default morph key type for migrations to ULIDs.
     */
    public static function morphUsingUlids(): void
    {
        static::defaultMorphKeyType('ulid');
    }

    /**
     * Create a database in the schema.
     *
     * @param  string  $name
     * @return bool
     */
    public function createDatabase($name)
    {
        return $this->connection->statement(
            $this->grammar->compileCreateDatabase($name)
        );
    }

    /**
     * Drop a database from the schema if the database exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function dropDatabaseIfExists($name)
    {
        return $this->connection->statement(
            $this->grammar->compileDropDatabaseIfExists($name)
        );
    }

    /**
     * Get the schemas that belong to the connection.
     *
     * @return list<array{name: string, path: string|null, default: bool}>
     */
    public function getSchemas(): array
    {
        return $this->connection->getPostProcessor()->processSchemas(
            $this->connection->selectFromWriteConnection($this->grammar->compileSchemas())
        );
    }

    /**
     * Determine if the given table exists.
     *
     * @param  string  $table
     * @return bool
     */
    public function hasTable($table)
    {
        [$schema, $table] = $this->parseSchemaAndTable($table);

        $table = $this->connection->getTablePrefix().$table;

        if ($sql = $this->grammar->compileTableExists($schema, $table)) {
            return (bool) $this->connection->scalar($sql);
        }

        foreach ($this->getTables($schema ?? $this->getCurrentSchemaName()) as $value) {
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
    public function hasView($view): bool
    {
        [$schema, $view] = $this->parseSchemaAndTable($view);

        $view = $this->connection->getTablePrefix().$view;

        foreach ($this->getViews($schema ?? $this->getCurrentSchemaName()) as $value) {
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
    public function getTables($schema = null): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->selectFromWriteConnection($this->grammar->compileTables($schema))
        );
    }

    /**
     * Get the names of the tables that belong to the connection.
     *
     * @param  string|string[]|null  $schema
     * @param  bool  $schemaQualified
     * @return list<string>
     */
    public function getTableListing($schema = null, $schemaQualified = true): array
    {
        return array_column(
            $this->getTables($schema),
            $schemaQualified ? 'schema_qualified_name' : 'name'
        );
    }

    /**
     * Get the views that belong to the connection.
     *
     * @param  string|string[]|null  $schema
     * @return list<array{name: string, schema: string|null, schema_qualified_name: string, definition: string}>
     */
    public function getViews($schema = null): array
    {
        return $this->connection->getPostProcessor()->processViews(
            $this->connection->selectFromWriteConnection($this->grammar->compileViews($schema))
        );
    }

    /**
     * Get the user-defined types that belong to the connection.
     *
     * @param  string|string[]|null  $schema
     * @return list<array{name: string, schema: string, type: string, type: string, category: string, implicit: bool}>
     */
    public function getTypes($schema = null)
    {
        return $this->connection->getPostProcessor()->processTypes(
            $this->connection->selectFromWriteConnection($this->grammar->compileTypes($schema))
        );
    }

    /**
     * Determine if the given table has a given column.
     *
     * @param  string  $table
     * @param  string  $column
     */
    public function hasColumn($table, $column): bool
    {
        return in_array(
            strtolower($column),
            array_map(strtolower(...), $this->getColumnListing($table))
        );
    }

    /**
     * Determine if the given table has given columns.
     *
     * @param  string  $table
     * @param  array<string>  $columns
     */
    public function hasColumns($table, array $columns): bool
    {
        $tableColumns = array_map(strtolower(...), $this->getColumnListing($table));

        foreach ($columns as $column) {
            if (! in_array(strtolower($column), $tableColumns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute a table builder callback if the given table has a given column.
     */
    public function whenTableHasColumn(string $table, string $column, Closure $callback): void
    {
        if ($this->hasColumn($table, $column)) {
            $this->table($table, fn (Blueprint $table) => $callback($table));
        }
    }

    /**
     * Execute a table builder callback if the given table doesn't have a given column.
     */
    public function whenTableDoesntHaveColumn(string $table, string $column, Closure $callback): void
    {
        if (! $this->hasColumn($table, $column)) {
            $this->table($table, fn (Blueprint $table) => $callback($table));
        }
    }

    /**
     * Execute a table builder callback if the given table has a given index.
     */
    public function whenTableHasIndex(string $table, string|array $index, Closure $callback, ?string $type = null): void
    {
        if ($this->hasIndex($table, $index, $type)) {
            $this->table($table, fn (Blueprint $table) => $callback($table));
        }
    }

    /**
     * Execute a table builder callback if the given table doesn't have a given index.
     */
    public function whenTableDoesntHaveIndex(string $table, string|array $index, Closure $callback, ?string $type = null): void
    {
        if (! $this->hasIndex($table, $index, $type)) {
            $this->table($table, fn (Blueprint $table) => $callback($table));
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
    public function getColumnType($table, $column, $fullDefinition = false)
    {
        $columns = $this->getColumns($table);

        foreach ($columns as $value) {
            if (strtolower($value['name']) === strtolower($column)) {
                return $fullDefinition ? $value['type'] : $value['type_name'];
            }
        }

        throw new InvalidArgumentException("There is no column with name '$column' on table '$table'.");
    }

    /**
     * Get the column listing for a given table.
     *
     * @param  string  $table
     * @return list<string>
     */
    public function getColumnListing($table): array
    {
        return array_column($this->getColumns($table), 'name');
    }

    /**
     * Get the columns for a given table.
     *
     * @param  string  $table
     * @return list<array{name: string, type: string, type_name: string, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null}>
     */
    public function getColumns($table)
    {
        [$schema, $table] = $this->parseSchemaAndTable($table);

        $table = $this->connection->getTablePrefix().$table;

        return $this->connection->getPostProcessor()->processColumns(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileColumns($schema, $table)
            )
        );
    }

    /**
     * Get the indexes for a given table.
     *
     * @param  string  $table
     * @return list<array{name: string, columns: list<string>, type: string, unique: bool, primary: bool}>
     */
    public function getIndexes($table)
    {
        [$schema, $table] = $this->parseSchemaAndTable($table);

        $table = $this->connection->getTablePrefix().$table;

        return $this->connection->getPostProcessor()->processIndexes(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileIndexes($schema, $table)
            )
        );
    }

    /**
     * Get the names of the indexes for a given table.
     *
     * @param  string  $table
     * @return list<string>
     */
    public function getIndexListing($table): array
    {
        return array_column($this->getIndexes($table), 'name');
    }

    /**
     * Determine if the given table has a given index.
     *
     * @param  string  $table
     * @param  string|array  $index
     * @param  string|null  $type
     */
    public function hasIndex($table, $index, $type = null): bool
    {
        $type = is_null($type) ? $type : strtolower($type);

        foreach ($this->getIndexes($table) as $value) {
            $typeMatches = is_null($type)
                || ($type === 'primary' && $value['primary'])
                || ($type === 'unique' && $value['unique'])
                || $type === $value['type'];

            if (($value['name'] === $index || $value['columns'] === $index) && $typeMatches) {
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
    public function getForeignKeys($table)
    {
        [$schema, $table] = $this->parseSchemaAndTable($table);

        $table = $this->connection->getTablePrefix().$table;

        return $this->connection->getPostProcessor()->processForeignKeys(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileForeignKeys($schema, $table)
            )
        );
    }

    /**
     * Modify a table on the schema.
     *
     * @param  string  $table
     */
    public function table($table, Closure $callback): void
    {
        $this->build($this->createBlueprint($table, $callback));
    }

    /**
     * Create a new table on the schema.
     *
     * @param  string  $table
     */
    public function create($table, Closure $callback): void
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($callback): void {
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
        $this->build(tap($this->createBlueprint($table), function ($blueprint): void {
            $blueprint->drop();
        }));
    }

    /**
     * Drop a table from the schema if it exists.
     *
     * @param  string  $table
     */
    public function dropIfExists($table): void
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint): void {
            $blueprint->dropIfExists();
        }));
    }

    /**
     * Drop columns from a table schema.
     *
     * @param  string  $table
     * @param  string|array<string>  $columns
     */
    public function dropColumns($table, $columns): void
    {
        $this->table($table, function (Blueprint $blueprint) use ($columns): void {
            $blueprint->dropColumn($columns);
        });
    }

    /**
     * Drop all tables from the database.
     *
     *
     * @throws \LogicException
     */
    public function dropAllTables(): never
    {
        throw new LogicException('This database driver does not support dropping all tables.');
    }

    /**
     * Drop all views from the database.
     *
     *
     * @throws \LogicException
     */
    public function dropAllViews(): never
    {
        throw new LogicException('This database driver does not support dropping all views.');
    }

    /**
     * Drop all types from the database.
     *
     *
     * @throws \LogicException
     */
    public function dropAllTypes(): never
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
        $this->build(tap($this->createBlueprint($from), function ($blueprint) use ($to): void {
            $blueprint->rename($to);
        }));
    }

    /**
     * Enable foreign key constraints.
     *
     * @return bool
     */
    public function enableForeignKeyConstraints()
    {
        return $this->connection->statement(
            $this->grammar->compileEnableForeignKeyConstraints()
        );
    }

    /**
     * Disable foreign key constraints.
     *
     * @return bool
     */
    public function disableForeignKeyConstraints()
    {
        return $this->connection->statement(
            $this->grammar->compileDisableForeignKeyConstraints()
        );
    }

    /**
     * Disable foreign key constraints during the execution of a callback.
     *
     * @template TReturn
     *
     * @param  (\Closure(): TReturn)  $callback
     * @return TReturn
     */
    public function withoutForeignKeyConstraints(Closure $callback)
    {
        $this->disableForeignKeyConstraints();

        try {
            return $callback();
        } finally {
            $this->enableForeignKeyConstraints();
        }
    }

    /**
     * Create the vector extension on the schema if it does not exist.
     *
     * @param  string|null  $schema
     */
    public function ensureVectorExtensionExists($schema = null): void
    {
        $this->ensureExtensionExists('vector', $schema);
    }

    /**
     * Create a new extension on the schema if it does not exist.
     *
     * @param  string  $name
     * @param  string|null  $schema
     */
    public function ensureExtensionExists($name, $schema = null): void
    {
        if (! $this->getConnection() instanceof PostgresConnection) {
            throw new RuntimeException('Extensions are only supported by Postgres.');
        }

        $name = $this->getConnection()->getSchemaGrammar()->wrap($name);

        $this->getConnection()->statement(match (filled($schema)) {
            true => "create extension if not exists {$name} schema {$this->getConnection()->getSchemaGrammar()->wrap($schema)}",
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
    protected function createBlueprint($table, ?Closure $callback = null)
    {
        $connection = $this->connection;

        if (isset($this->resolver)) {
            return call_user_func($this->resolver, $connection, $table, $callback);
        }

        return Container::getInstance()->make(Blueprint::class, compact('connection', 'table', 'callback'));
    }

    /**
     * Get the names of the current schemas for the connection.
     *
     * @return string[]|null
     */
    public function getCurrentSchemaListing(): null
    {
        return null;
    }

    /**
     * Get the default schema name for the connection.
     *
     * @return string|null
     */
    public function getCurrentSchemaName()
    {
        return $this->getCurrentSchemaListing()[0] ?? null;
    }

    /**
     * Parse the given database object reference and extract the schema and table.
     *
     * @param  string  $reference
     * @param  string|bool|null  $withDefaultSchema
     * @return array{string|null, string}
     */
    public function parseSchemaAndTable($reference, $withDefaultSchema = null): array
    {
        $segments = explode('.', $reference);

        if (count($segments) > 2) {
            throw new InvalidArgumentException(
                "Using three-part references is not supported, you may use `Schema::connection('{$segments[0]}')` instead."
            );
        }

        $table = $segments[1] ?? $segments[0];

        $schema = match (true) {
            isset($segments[1]) => $segments[0],
            is_string($withDefaultSchema) => $withDefaultSchema,
            $withDefaultSchema => $this->getCurrentSchemaName(),
            default => null,
        };

        return [$schema, $table];
    }

    /**
     * Get the database connection instance.
     */
    public function getConnection(): \Illuminate\Database\Connection
    {
        return $this->connection;
    }

    /**
     * Set the Schema Blueprint resolver callback.
     *
     * @param  \Closure(\Illuminate\Database\Connection, string, \Closure|null): \Illuminate\Database\Schema\Blueprint  $resolver
     */
    public function blueprintResolver(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }
}
