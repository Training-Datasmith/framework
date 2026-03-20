<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema\Grammars;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Column_Definition;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use RuntimeException;
class My_Sql_Grammar extends Grammar
{
    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected $modifiers = ['Unsigned', 'Charset', 'Collate', 'VirtualAs', 'StoredAs', 'Nullable', 'Default', 'OnUpdate', 'Invisible', 'Increment', 'Comment', 'After', 'First'];
    /**
     * The possible column serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];
    /**
     * The commands to be executed outside of create or alter commands.
     *
     * @var string[]
     */
    protected $fluent_commands = ['AutoIncrementStartingValues'];
    /**
     * Compile a create database command.
     *
     * @param  string  $name
     * @return string
     */
    public function compile_create_database($name)
    {
        $sql = parent::compile_create_database($name);
        if ($charset = $this->connection->get_config('charset')) {
            $sql .= sprintf(' default character set %s', $this->wrap_value($charset));
        }
        if ($collation = $this->connection->get_config('collation')) {
            $sql .= sprintf(' default collate %s', $this->wrap_value($collation));
        }
        return $sql;
    }
    /**
     * Compile the query to determine the schemas.
     */
    public function compile_schemas(): string
    {
        return 'select schema_name as name, schema_name = schema() as `default` from information_schema.schemata where ' . $this->compile_schema_where_clause(null, 'schema_name') . ' order by schema_name';
    }
    /**
     * Compile the query to determine if the given table exists.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_table_exists($schema, $table): string
    {
        return sprintf('select exists (select 1 from information_schema.tables where ' . "table_schema = %s and table_name = %s and table_type in ('BASE TABLE', 'SYSTEM VERSIONED')) as `exists`", $schema ? $this->quote_string($schema) : 'schema()', $this->quote_string($table));
    }
    /**
     * Compile the query to determine the tables.
     *
     * @param  string|string[]|null  $schema
     */
    public function compile_tables($schema): string
    {
        return sprintf('select table_name as `name`, table_schema as `schema`, (data_length + index_length) as `size`, ' . 'table_comment as `comment`, engine as `engine`, table_collation as `collation` ' . "from information_schema.tables where table_type in ('BASE TABLE', 'SYSTEM VERSIONED') and " . $this->compile_schema_where_clause($schema, 'table_schema') . ' order by table_schema, table_name', $this->quote_string($schema));
    }
    /**
     * Compile the query to determine the views.
     *
     * @param  string|string[]|null  $schema
     */
    public function compile_views($schema): string
    {
        return 'select table_name as `name`, table_schema as `schema`, view_definition as `definition` ' . 'from information_schema.views where ' . $this->compile_schema_where_clause($schema, 'table_schema') . ' order by table_schema, table_name';
    }
    /**
     * Compile the query to compare the schema.
     *
     * @param  string|string[]|null  $schema
     */
    protected function compile_schema_where_clause($schema, string $column): string
    {
        return $column . match (true) {
            !empty($schema) && is_array($schema) => ' in (' . $this->quote_string($schema) . ')',
            !empty($schema) => ' = ' . $this->quote_string($schema),
            default => " not in ('information_schema', 'mysql', 'ndbinfo', 'performance_schema', 'sys')",
        };
    }
    /**
     * Compile the query to determine the columns.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_columns($schema, $table): string
    {
        return sprintf('select column_name as `name`, data_type as `type_name`, column_type as `type`, ' . 'collation_name as `collation`, is_nullable as `nullable`, ' . 'column_default as `default`, column_comment as `comment`, ' . 'generation_expression as `expression`, extra as `extra` ' . 'from information_schema.columns where table_schema = %s and table_name = %s ' . 'order by ordinal_position asc', $schema ? $this->quote_string($schema) : 'schema()', $this->quote_string($table));
    }
    /**
     * Compile the query to determine the indexes.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_indexes($schema, $table): string
    {
        return sprintf('select index_name as `name`, group_concat(column_name order by seq_in_index) as `columns`, ' . 'index_type as `type`, not non_unique as `unique` ' . 'from information_schema.statistics where table_schema = %s and table_name = %s ' . 'group by index_name, index_type, non_unique', $schema ? $this->quote_string($schema) : 'schema()', $this->quote_string($table));
    }
    /**
     * Compile the query to determine the foreign keys.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_foreign_keys($schema, $table): string
    {
        return sprintf('select kc.constraint_name as `name`, ' . 'group_concat(kc.column_name order by kc.ordinal_position) as `columns`, ' . 'kc.referenced_table_schema as `foreign_schema`, ' . 'kc.referenced_table_name as `foreign_table`, ' . 'group_concat(kc.referenced_column_name order by kc.ordinal_position) as `foreign_columns`, ' . 'rc.update_rule as `on_update`, ' . 'rc.delete_rule as `on_delete` ' . 'from information_schema.key_column_usage kc join information_schema.referential_constraints rc ' . 'on kc.constraint_schema = rc.constraint_schema and kc.constraint_name = rc.constraint_name ' . 'where kc.table_schema = %s and kc.table_name = %s and kc.referenced_table_name is not null ' . 'group by kc.constraint_name, kc.referenced_table_schema, kc.referenced_table_name, rc.update_rule, rc.delete_rule', $schema ? $this->quote_string($schema) : 'schema()', $this->quote_string($table));
    }
    /**
     * Compile a create table command.
     */
    public function compile_create(Blueprint $blueprint, Fluent $command): string
    {
        $sql = $this->compile_create_table($blueprint, $command);
        // Once we have the primary SQL, we can add the encoding option to the SQL for
        // the table.  Then, we can check if a storage engine has been supplied for
        // the table. If so, we will add the engine declaration to the SQL query.
        $sql = $this->compile_create_encoding($sql, $blueprint);
        // Finally, we will append the engine configuration onto this SQL statement as
        // the final thing we do before returning this finished SQL. Once this gets
        // added the query will be ready to execute against the real connections.
        return $this->compile_create_engine($sql, $blueprint);
    }
    /**
     * Create the main create table clause.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     */
    protected function compile_create_table($blueprint, $command): string
    {
        $table_structure = $this->get_columns($blueprint);
        if ($primary_key = $this->get_command_by_name($blueprint, 'primary')) {
            $table_structure[] = sprintf('primary key %s(%s)', $primary_key->algorithm ? 'using ' . $primary_key->algorithm : '', $this->columnize($primary_key->columns));
            $primary_key->should_be_skipped = true;
        }
        return sprintf('%s table %s (%s)', $blueprint->temporary ? 'create temporary' : 'create', $this->wrap_table($blueprint), implode(', ', $table_structure));
    }
    /**
     * Append the character set specifications to a command.
     *
     * @param  string  $sql
     */
    protected function compile_create_encoding($sql, Blueprint $blueprint): string
    {
        // First we will set the character set if one has been set on either the create
        // blueprint itself or on the root configuration for the connection that the
        // table is being created on. We will add these to the create table query.
        if (isset($blueprint->charset)) {
            $sql .= ' default character set ' . $blueprint->charset;
        } elseif (!is_null($charset = $this->connection->get_config('charset'))) {
            $sql .= ' default character set ' . $charset;
        }
        // Next we will add the collation to the create table statement if one has been
        // added to either this create table blueprint or the configuration for this
        // connection that the query is targeting. We'll add it to this SQL query.
        if (isset($blueprint->collation)) {
            $sql .= " collate '{$blueprint->collation}'";
        } elseif (!is_null($collation = $this->connection->get_config('collation'))) {
            $sql .= " collate '{$collation}'";
        }
        return $sql;
    }
    /**
     * Append the engine specifications to a command.
     */
    protected function compile_create_engine(string $sql, Blueprint $blueprint): string
    {
        if (isset($blueprint->engine)) {
            return $sql . ' engine = ' . $blueprint->engine;
        }
        if (!is_null($engine = $this->connection->get_config('engine'))) {
            return $sql . ' engine = ' . $engine;
        }
        return $sql;
    }
    /**
     * Compile an add column command.
     */
    public function compile_add(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add %s%s%s', $this->wrap_table($blueprint), $this->get_column($blueprint, $command->column), $command->column->instant ? ', algorithm=instant' : '', $command->column->lock ? ', lock=' . $command->column->lock : '');
    }
    /**
     * Compile the auto-incrementing column starting values.
     *
     * @return string
     */
    public function compile_auto_increment_starting_values(Blueprint $blueprint, Fluent $command)
    {
        if ($command->column->auto_increment && $value = $command->column->get('startingValue', $command->column->get('from'))) {
            return 'alter table ' . $this->wrap_table($blueprint) . ' auto_increment = ' . $value;
        }
    }
    /** @inheritDoc */
    public function compile_rename_column(Blueprint $blueprint, Fluent $command)
    {
        $is_maria = $this->connection->is_maria();
        $version = $this->connection->get_server_version();
        if ($is_maria && version_compare($version, '10.5.2', '<') || !$is_maria && version_compare($version, '8.0.3', '<')) {
            return $this->compile_legacy_rename_column($blueprint, $command);
        }
        return parent::compile_rename_column($blueprint, $command);
    }
    /**
     * Compile a rename column command for legacy versions of MySQL.
     */
    protected function compile_legacy_rename_column(Blueprint $blueprint, Fluent $command): string
    {
        $column = (new Collection($this->connection->get_schema_builder()->get_columns($blueprint->get_table())))->first_where('name', $command->from);
        $modifiers = $this->add_modifiers($column['type'], $blueprint, new Column_Definition(['change' => true, 'type' => match ($column['type_name']) {
            'bigint' => 'bigInteger',
            'int' => 'integer',
            'mediumint' => 'mediumInteger',
            'smallint' => 'smallInteger',
            'tinyint' => 'tinyInteger',
            default => $column['type_name'],
        }, 'nullable' => $column['nullable'], 'default' => $column['default'] && (str_starts_with(strtolower((string) $column['default']), 'current_timestamp') || $column['default'] === 'NULL') ? new Expression($column['default']) : $column['default'], 'autoIncrement' => $column['auto_increment'], 'collation' => $column['collation'], 'comment' => $column['comment'], 'virtualAs' => !is_null($column['generation']) && $column['generation']['type'] === 'virtual' ? $column['generation']['expression'] : null, 'storedAs' => !is_null($column['generation']) && $column['generation']['type'] === 'stored' ? $column['generation']['expression'] : null]));
        return sprintf('alter table %s change %s %s %s', $this->wrap_table($blueprint), $this->wrap($command->from), $this->wrap($command->to), $modifiers);
    }
    /** @inheritDoc */
    public function compile_change(Blueprint $blueprint, Fluent $command)
    {
        $column = $command->column;
        $sql = sprintf('alter table %s %s %s%s %s', $this->wrap_table($blueprint), is_null($column->rename_to) ? 'modify' : 'change', $this->wrap($column), is_null($column->rename_to) ? '' : ' ' . $this->wrap($column->rename_to), $this->get_type($column));
        $sql = $this->add_modifiers($sql, $blueprint, $column);
        if ($column->instant) {
            $sql .= ', algorithm=instant';
        }
        if ($column->lock) {
            $sql .= ', lock=' . $column->lock;
        }
        return $sql;
    }
    /**
     * Compile a primary key command.
     */
    public function compile_primary(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add primary key %s(%s)%s', $this->wrap_table($blueprint), $command->algorithm ? 'using ' . $command->algorithm : '', $this->columnize($command->columns), $command->lock ? ', lock=' . $command->lock : '');
    }
    /**
     * Compile a unique key command.
     */
    public function compile_unique(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_key($blueprint, $command, 'unique');
    }
    /**
     * Compile a plain index key command.
     */
    public function compile_index(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_key($blueprint, $command, 'index');
    }
    /**
     * Compile a fulltext index key command.
     */
    public function compile_full_text(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_key($blueprint, $command, 'fulltext');
    }
    /**
     * Compile a spatial index key command.
     */
    public function compile_spatial_index(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_key($blueprint, $command, 'spatial index');
    }
    /**
     * Compile an index creation command.
     *
     * @param  string  $type
     */
    protected function compile_key(Blueprint $blueprint, Fluent $command, $type): string
    {
        return sprintf('alter table %s add %s %s%s(%s)%s', $this->wrap_table($blueprint), $type, $this->wrap($command->index), $command->algorithm ? ' using ' . $command->algorithm : '', $this->columnize($command->columns), $command->lock ? ', lock=' . $command->lock : '');
    }
    /**
     * Compile a drop table command.
     */
    public function compile_drop(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop table ' . $this->wrap_table($blueprint);
    }
    /**
     * Compile a drop table (if exists) command.
     */
    public function compile_drop_if_exists(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop table if exists ' . $this->wrap_table($blueprint);
    }
    /**
     * Compile a drop column command.
     */
    public function compile_drop_column(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->prefix_array('drop', $this->wrap_array($command->columns));
        $sql = 'alter table ' . $this->wrap_table($blueprint) . ' ' . implode(', ', $columns);
        if ($command->instant) {
            $sql .= ', algorithm=instant';
        }
        if ($command->lock) {
            $sql .= ', lock=' . $command->lock;
        }
        return $sql;
    }
    /**
     * Compile a drop primary key command.
     */
    public function compile_drop_primary(Blueprint $blueprint, Fluent $command): string
    {
        return 'alter table ' . $this->wrap_table($blueprint) . ' drop primary key';
    }
    /**
     * Compile a drop unique key command.
     */
    public function compile_drop_unique(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);
        return "alter table {$this->wrap_table($blueprint)} drop index {$index}";
    }
    /**
     * Compile a drop index command.
     */
    public function compile_drop_index(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);
        return "alter table {$this->wrap_table($blueprint)} drop index {$index}";
    }
    /**
     * Compile a drop fulltext index command.
     */
    public function compile_drop_full_text(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_drop_index($blueprint, $command);
    }
    /**
     * Compile a drop spatial index command.
     */
    public function compile_drop_spatial_index(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_drop_index($blueprint, $command);
    }
    /**
     * Compile a foreign key command.
     *
     * @return string
     */
    public function compile_foreign(Blueprint $blueprint, Fluent $command)
    {
        $sql = parent::compile_foreign($blueprint, $command);
        if ($command->lock) {
            $sql .= ', lock=' . $command->lock;
        }
        return $sql;
    }
    /**
     * Compile a drop foreign key command.
     */
    public function compile_drop_foreign(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);
        return "alter table {$this->wrap_table($blueprint)} drop foreign key {$index}";
    }
    /**
     * Compile a rename table command.
     */
    public function compile_rename(Blueprint $blueprint, Fluent $command): string
    {
        $from = $this->wrap_table($blueprint);
        return "rename table {$from} to " . $this->wrap_table($command->to);
    }
    /**
     * Compile a rename index command.
     */
    public function compile_rename_index(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s rename index %s to %s', $this->wrap_table($blueprint), $this->wrap($command->from), $this->wrap($command->to));
    }
    /**
     * Compile the SQL needed to drop all tables.
     *
     * @param  array<string>  $tables
     */
    public function compile_drop_all_tables($tables): string
    {
        return 'drop table ' . implode(', ', $this->escape_names($tables));
    }
    /**
     * Compile the SQL needed to drop all views.
     *
     * @param  array<string>  $views
     */
    public function compile_drop_all_views($views): string
    {
        return 'drop view ' . implode(', ', $this->escape_names($views));
    }
    /**
     * Compile the command to enable foreign key constraints.
     */
    public function compile_enable_foreign_key_constraints(): string
    {
        return 'SET FOREIGN_KEY_CHECKS=1;';
    }
    /**
     * Compile the command to disable foreign key constraints.
     */
    public function compile_disable_foreign_key_constraints(): string
    {
        return 'SET FOREIGN_KEY_CHECKS=0;';
    }
    /**
     * Compile a table comment command.
     */
    public function compile_table_comment(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s comment = %s', $this->wrap_table($blueprint), "'" . str_replace("'", "''", $command->comment) . "'");
    }
    /**
     * Quote-escape the given tables, views, or types.
     *
     * @param  array<string>  $names
     * @return array<string>
     */
    public function escape_names($names): array
    {
        return array_map(fn(string $name): string => (new Collection(explode('.', $name)))->map($this->wrap_value(...))->implode('.'), $names);
    }
    /**
     * Create the column definition for a char type.
     */
    protected function type_char(Fluent $column): string
    {
        return "char({$column->length})";
    }
    /**
     * Create the column definition for a string type.
     */
    protected function type_string(Fluent $column): string
    {
        return "varchar({$column->length})";
    }
    /**
     * Create the column definition for a tiny text type.
     */
    protected function type_tiny_text(Fluent $column): string
    {
        return 'tinytext';
    }
    /**
     * Create the column definition for a text type.
     */
    protected function type_text(Fluent $column): string
    {
        return 'text';
    }
    /**
     * Create the column definition for a medium text type.
     */
    protected function type_medium_text(Fluent $column): string
    {
        return 'mediumtext';
    }
    /**
     * Create the column definition for a long text type.
     */
    protected function type_long_text(Fluent $column): string
    {
        return 'longtext';
    }
    /**
     * Create the column definition for a big integer type.
     */
    protected function type_big_integer(Fluent $column): string
    {
        return 'bigint';
    }
    /**
     * Create the column definition for an integer type.
     */
    protected function type_integer(Fluent $column): string
    {
        return 'int';
    }
    /**
     * Create the column definition for a medium integer type.
     */
    protected function type_medium_integer(Fluent $column): string
    {
        return 'mediumint';
    }
    /**
     * Create the column definition for a tiny integer type.
     */
    protected function type_tiny_integer(Fluent $column): string
    {
        return 'tinyint';
    }
    /**
     * Create the column definition for a small integer type.
     */
    protected function type_small_integer(Fluent $column): string
    {
        return 'smallint';
    }
    /**
     * Create the column definition for a float type.
     */
    protected function type_float(Fluent $column): string
    {
        if ($column->precision) {
            return "float({$column->precision})";
        }
        return 'float';
    }
    /**
     * Create the column definition for a double type.
     */
    protected function type_double(Fluent $column): string
    {
        return 'double';
    }
    /**
     * Create the column definition for a decimal type.
     */
    protected function type_decimal(Fluent $column): string
    {
        return "decimal({$column->total}, {$column->places})";
    }
    /**
     * Create the column definition for a boolean type.
     */
    protected function type_boolean(Fluent $column): string
    {
        return 'tinyint(1)';
    }
    /**
     * Create the column definition for an enumeration type.
     */
    protected function type_enum(Fluent $column): string
    {
        return sprintf('enum(%s)', $this->quote_string($column->allowed));
    }
    /**
     * Create the column definition for a set enumeration type.
     */
    protected function type_set(Fluent $column): string
    {
        return sprintf('set(%s)', $this->quote_string($column->allowed));
    }
    /**
     * Create the column definition for a json type.
     */
    protected function type_json(Fluent $column): string
    {
        return 'json';
    }
    /**
     * Create the column definition for a jsonb type.
     */
    protected function type_jsonb(Fluent $column): string
    {
        return 'json';
    }
    /**
     * Create the column definition for a date type.
     */
    protected function type_date(Fluent $column): string
    {
        $is_maria = $this->connection->is_maria();
        $version = $this->connection->get_server_version();
        if ($is_maria || !$is_maria && version_compare($version, '8.0.13', '>=')) {
            if ($column->use_current) {
                $column->default(new Expression('(CURDATE())'));
            }
        }
        return 'date';
    }
    /**
     * Create the column definition for a date-time type.
     */
    protected function type_date_time(Fluent $column): string
    {
        $current = $column->precision ? "CURRENT_TIMESTAMP({$column->precision})" : 'CURRENT_TIMESTAMP';
        if ($column->use_current) {
            $column->default(new Expression($current));
        }
        if ($column->use_current_on_update) {
            $column->on_update(new Expression($current));
        }
        return $column->precision ? "datetime({$column->precision})" : 'datetime';
    }
    /**
     * Create the column definition for a date-time (with time zone) type.
     */
    protected function type_date_time_tz(Fluent $column): string
    {
        return $this->type_date_time($column);
    }
    /**
     * Create the column definition for a time type.
     */
    protected function type_time(Fluent $column): string
    {
        return $column->precision ? "time({$column->precision})" : 'time';
    }
    /**
     * Create the column definition for a time (with time zone) type.
     */
    protected function type_time_tz(Fluent $column): string
    {
        return $this->type_time($column);
    }
    /**
     * Create the column definition for a timestamp type.
     */
    protected function type_timestamp(Fluent $column): string
    {
        $current = $column->precision ? "CURRENT_TIMESTAMP({$column->precision})" : 'CURRENT_TIMESTAMP';
        if ($column->use_current) {
            $column->default(new Expression($current));
        }
        if ($column->use_current_on_update) {
            $column->on_update(new Expression($current));
        }
        return $column->precision ? "timestamp({$column->precision})" : 'timestamp';
    }
    /**
     * Create the column definition for a timestamp (with time zone) type.
     */
    protected function type_timestamp_tz(Fluent $column): string
    {
        return $this->type_timestamp($column);
    }
    /**
     * Create the column definition for a year type.
     */
    protected function type_year(Fluent $column): string
    {
        $is_maria = $this->connection->is_maria();
        $version = $this->connection->get_server_version();
        if ($is_maria || !$is_maria && version_compare($version, '8.0.13', '>=')) {
            if ($column->use_current) {
                $column->default(new Expression('(YEAR(CURDATE()))'));
            }
        }
        return 'year';
    }
    /**
     * Create the column definition for a binary type.
     */
    protected function type_binary(Fluent $column): string
    {
        if ($column->length) {
            return $column->fixed ? "binary({$column->length})" : "varbinary({$column->length})";
        }
        return 'blob';
    }
    /**
     * Create the column definition for a uuid type.
     */
    protected function type_uuid(Fluent $column): string
    {
        return 'char(36)';
    }
    /**
     * Create the column definition for an IP address type.
     */
    protected function type_ip_address(Fluent $column): string
    {
        return 'varchar(45)';
    }
    /**
     * Create the column definition for a MAC address type.
     */
    protected function type_mac_address(Fluent $column): string
    {
        return 'varchar(17)';
    }
    /**
     * Create the column definition for a spatial Geometry type.
     */
    protected function type_geometry(Fluent $column): string
    {
        $subtype = $column->subtype ? strtolower($column->subtype) : null;
        if (!in_array($subtype, ['point', 'linestring', 'polygon', 'geometrycollection', 'multipoint', 'multilinestring', 'multipolygon'])) {
            $subtype = null;
        }
        return sprintf('%s%s', $subtype ?? 'geometry', match (true) {
            $column->srid && $this->connection->is_maria() => ' ref_system_id=' . $column->srid,
            (bool) $column->srid => ' srid ' . $column->srid,
            default => '',
        });
    }
    /**
     * Create the column definition for a spatial Geography type.
     */
    protected function type_geography(Fluent $column): string
    {
        return $this->type_geometry($column);
    }
    /**
     * Create the column definition for a generated, computed column type.
     *
     *
     * @throws \RuntimeException
     */
    protected function type_computed(Fluent $column): never
    {
        throw new RuntimeException('This database driver requires a type, see the virtualAs / storedAs modifiers.');
    }
    /**
     * Create the column definition for a vector type.
     */
    protected function type_vector(Fluent $column): string
    {
        return isset($column->dimensions) && $column->dimensions !== '' ? "vector({$column->dimensions})" : 'vector';
    }
    /**
     * Get the SQL for a generated virtual column modifier.
     *
     * @return string|null
     */
    protected function modify_virtual_as(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($virtual_as = $column->virtual_as_json)) {
            if ($this->is_json_selector($virtual_as)) {
                $virtual_as = $this->wrap_json_selector($virtual_as);
            }
            return " as ({$virtual_as})";
        }
        if (!is_null($virtual_as = $column->virtual_as)) {
            return " as ({$this->get_value($virtual_as)})";
        }
    }
    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @return string|null
     */
    protected function modify_stored_as(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($stored_as = $column->stored_as_json)) {
            if ($this->is_json_selector($stored_as)) {
                $stored_as = $this->wrap_json_selector($stored_as);
            }
            return " as ({$stored_as}) stored";
        }
        if (!is_null($stored_as = $column->stored_as)) {
            return " as ({$this->get_value($stored_as)}) stored";
        }
    }
    /**
     * Get the SQL for an unsigned column modifier.
     *
     * @return string|null
     */
    protected function modify_unsigned(Blueprint $blueprint, Fluent $column)
    {
        if ($column->unsigned) {
            return ' unsigned';
        }
    }
    /**
     * Get the SQL for a character set column modifier.
     *
     * @return string|null
     */
    protected function modify_charset(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->charset)) {
            return ' character set ' . $column->charset;
        }
    }
    /**
     * Get the SQL for a collation column modifier.
     *
     * @return string|null
     */
    protected function modify_collate(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->collation)) {
            return " collate '{$column->collation}'";
        }
    }
    /**
     * Get the SQL for a nullable column modifier.
     *
     * @return string|null
     */
    protected function modify_nullable(Blueprint $blueprint, Fluent $column)
    {
        if (is_null($column->virtual_as) && is_null($column->virtual_as_json) && is_null($column->stored_as) && is_null($column->stored_as_json)) {
            return $column->nullable ? ' null' : ' not null';
        }
        if ($column->nullable === false) {
            return ' not null';
        }
    }
    /**
     * Get the SQL for an invisible column modifier.
     *
     * @return string|null
     */
    protected function modify_invisible(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->invisible)) {
            return ' invisible';
        }
    }
    /**
     * Get the SQL for a default column modifier.
     *
     * @return string|null
     */
    protected function modify_default(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->default)) {
            return ' default ' . $this->get_default_value($column->default);
        }
    }
    /**
     * Get the SQL for an "on update" column modifier.
     *
     * @return string|null
     */
    protected function modify_on_update(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->on_update)) {
            return ' on update ' . $this->get_value($column->on_update);
        }
    }
    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @return string|null
     */
    protected function modify_increment(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->auto_increment) {
            return $this->has_command($blueprint, 'primary') || $column->change && !$column->primary ? ' auto_increment' : ' auto_increment primary key';
        }
    }
    /**
     * Get the SQL for a "first" column modifier.
     *
     * @return string|null
     */
    protected function modify_first(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->first)) {
            return ' first';
        }
    }
    /**
     * Get the SQL for an "after" column modifier.
     *
     * @return string|null
     */
    protected function modify_after(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->after)) {
            return ' after ' . $this->wrap($column->after);
        }
    }
    /**
     * Get the SQL for a "comment" column modifier.
     *
     * @return string|null
     */
    protected function modify_comment(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->comment)) {
            return " comment '" . addslashes($column->comment) . "'";
        }
    }
    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     */
    protected function wrap_value($value): string
    {
        if ($value !== '*') {
            return '`' . str_replace('`', '``', $value) . '`';
        }
        return $value;
    }
    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrap_json_selector($value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($value);
        return 'json_unquote(json_extract(' . $field . $path . '))';
    }
}