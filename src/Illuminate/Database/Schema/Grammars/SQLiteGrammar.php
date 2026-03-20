<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema\Grammars;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Index_Definition;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use RuntimeException;
class Sq_Lite_Grammar extends Grammar
{
    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected $modifiers = ['Increment', 'Nullable', 'Default', 'Collate', 'VirtualAs', 'StoredAs'];
    /**
     * The columns available as serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];
    /**
     * Get the commands to be compiled on the alter command.
     */
    public function get_alter_commands(): array
    {
        $alter_commands = ['change', 'primary', 'dropPrimary', 'foreign', 'dropForeign'];
        if (version_compare($this->connection->get_server_version(), '3.35', '<')) {
            $alter_commands[] = 'dropColumn';
        }
        return $alter_commands;
    }
    /**
     * Compile the query to determine the SQL text that describes the given object.
     *
     * @param  string|null  $schema
     * @param  string  $name
     * @param  string  $type
     */
    public function compile_sql_create_statement($schema, $name, $type = 'table'): string
    {
        return sprintf('select "sql" from %s.sqlite_master where type = %s and name = %s', $this->wrap_value($schema ?? 'main'), $this->quote_string($type), $this->quote_string($name));
    }
    /**
     * Compile the query to determine if the dbstat table is available.
     */
    public function compile_dbstat_exists(): string
    {
        return "select exists (select 1 from pragma_compile_options where compile_options = 'ENABLE_DBSTAT_VTAB') as enabled";
    }
    /**
     * Compile the query to determine the schemas.
     */
    public function compile_schemas(): string
    {
        return 'select name, file as path, name = \'main\' as "default" from pragma_database_list order by name';
    }
    /**
     * Compile the query to determine if the given table exists.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_table_exists($schema, $table): string
    {
        return sprintf('select exists (select 1 from %s.sqlite_master where name = %s and type = \'table\') as "exists"', $this->wrap_value($schema ?? 'main'), $this->quote_string($table));
    }
    /**
     * Compile the query to determine the tables.
     *
     * @param  string|string[]|null  $schema
     * @param  bool  $withSize
     */
    public function compile_tables($schema, $with_size = false): string
    {
        return 'select tl.name as name, tl.schema as schema' . ($with_size ? ', (select sum(s.pgsize) ' . 'from (select tl.name as name union select il.name as name from pragma_index_list(tl.name, tl.schema) as il) as es ' . 'join dbstat(tl.schema) as s on s.name = es.name) as size' : '') . ' from pragma_table_list as tl where' . match (true) {
            !empty($schema) && is_array($schema) => ' tl.schema in (' . $this->quote_string($schema) . ') and',
            !empty($schema) => ' tl.schema = ' . $this->quote_string($schema) . ' and',
            default => '',
        } . " tl.type in ('table', 'virtual') and tl.name not like 'sqlite\\_%' escape '\\' " . 'order by tl.schema, tl.name';
    }
    /**
     * Compile the query for legacy versions of SQLite to determine the tables.
     *
     * @param  string  $schema
     * @param  bool  $withSize
     */
    public function compile_legacy_tables($schema, $with_size = false): string
    {
        return $with_size ? sprintf('select m.tbl_name as name, %s as schema, sum(s.pgsize) as size from %s.sqlite_master as m ' . 'join dbstat(%s) as s on s.name = m.name ' . "where m.type in ('table', 'index') and m.tbl_name not like 'sqlite\\_%%' escape '\\' " . 'group by m.tbl_name ' . 'order by m.tbl_name', $this->quote_string($schema), $this->wrap_value($schema), $this->quote_string($schema)) : sprintf('select name, %s as schema from %s.sqlite_master ' . "where type = 'table' and name not like 'sqlite\\_%%' escape '\\' order by name", $this->quote_string($schema), $this->wrap_value($schema));
    }
    /**
     * Compile the query to determine the views.
     *
     * @param  string  $schema
     */
    public function compile_views($schema): string
    {
        return sprintf("select name, %s as schema, sql as definition from %s.sqlite_master where type = 'view' order by name", $this->quote_string($schema), $this->wrap_value($schema));
    }
    /**
     * Compile the query to determine the columns.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_columns($schema, $table): string
    {
        return sprintf('select name, type, not "notnull" as "nullable", dflt_value as "default", pk as "primary", hidden as "extra" ' . 'from pragma_table_xinfo(%s, %s) order by cid asc', $this->quote_string($table), $this->quote_string($schema ?? 'main'));
    }
    /**
     * Compile the query to determine the indexes.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_indexes($schema, $table): string
    {
        return sprintf('select \'primary\' as name, group_concat(col) as columns, 1 as "unique", 1 as "primary" ' . 'from (select name as col from pragma_table_xinfo(%s, %s) where pk > 0 order by pk, cid) group by name ' . 'union select name, group_concat(col) as columns, "unique", origin = \'pk\' as "primary" ' . 'from (select il.*, ii.name as col from pragma_index_list(%s, %s) il, pragma_index_info(il.name, %s) ii order by il.seq, ii.seqno) ' . 'group by name, "unique", "primary"', $table = $this->quote_string($table), $schema = $this->quote_string($schema ?? 'main'), $table, $schema, $schema);
    }
    /**
     * Compile the query to determine the foreign keys.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_foreign_keys($schema, $table): string
    {
        return sprintf('select group_concat("from") as columns, %s as foreign_schema, "table" as foreign_table, ' . 'group_concat("to") as foreign_columns, on_update, on_delete ' . 'from (select * from pragma_foreign_key_list(%s, %s) order by id desc, seq) ' . 'group by id, "table", on_update, on_delete', $schema = $this->quote_string($schema ?? 'main'), $this->quote_string($table), $schema);
    }
    /**
     * Compile a create table command.
     */
    public function compile_create(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('%s table %s (%s%s%s)', $blueprint->temporary ? 'create temporary' : 'create', $this->wrap_table($blueprint), implode(', ', $this->get_columns($blueprint)), $this->add_foreign_keys($this->get_commands_by_name($blueprint, 'foreign')), $this->add_primary_keys($this->get_command_by_name($blueprint, 'primary')));
    }
    /**
     * Get the foreign key syntax for a table creation statement.
     *
     * @param  \Illuminate\Database\Schema\ForeignKeyDefinition[]  $foreignKeys
     * @return string|null
     */
    protected function add_foreign_keys($foreign_keys)
    {
        return (new Collection($foreign_keys))->reduce(
            // Once we have all the foreign key commands for the table creation statement
            // we'll loop through each of them and add them to the create table SQL we
            // are building, since SQLite needs foreign keys on the tables creation.
            fn($sql, $foreign): string => $sql . $this->get_foreign_key($foreign),
            ''
        );
    }
    /**
     * Get the SQL for the foreign key.
     *
     * @param  \Illuminate\Support\Fluent  $foreign
     */
    protected function get_foreign_key($foreign): string
    {
        // We need to columnize the columns that the foreign key is being defined for
        // so that it is a properly formatted list. Once we have done this, we can
        // return the foreign key SQL declaration to the calling method for use.
        $sql = sprintf(', foreign key(%s) references %s(%s)', $this->columnize($foreign->columns), $this->wrap_table($foreign->on), $this->columnize((array) $foreign->references));
        if (!is_null($foreign->on_delete)) {
            $sql .= " on delete {$foreign->on_delete}";
        }
        // If this foreign key specifies the action to be taken on update we will add
        // that to the statement here. We'll append it to this SQL and then return
        // this SQL so we can keep adding any other foreign constraints to this.
        if (!is_null($foreign->on_update)) {
            $sql .= " on update {$foreign->on_update}";
        }
        return $sql;
    }
    /**
     * Get the primary key syntax for a table creation statement.
     *
     * @param  \Illuminate\Support\Fluent|null  $primary
     * @return string|null
     */
    protected function add_primary_keys($primary)
    {
        if (!is_null($primary)) {
            return ", primary key ({$this->columnize($primary->columns)})";
        }
    }
    /**
     * Compile alter table commands for adding columns.
     */
    public function compile_add(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add column %s', $this->wrap_table($blueprint), $this->get_column($blueprint, $command->column));
    }
    /**
     * Compile alter table command into a series of SQL statements.
     *
     * @return list<string>|string
     */
    public function compile_alter(Blueprint $blueprint, Fluent $command): array
    {
        $column_names = [];
        $auto_increment_column = null;
        $columns = (new Collection($blueprint->get_state()->get_columns()))->map(function ($column) use ($blueprint, &$column_names, &$auto_increment_column) {
            $name = $this->wrap($column);
            $auto_increment_column = $column->auto_increment ? $column->name : $auto_increment_column;
            if (is_null($column->virtual_as) && is_null($column->virtual_as_json) && is_null($column->stored_as) && is_null($column->stored_as_json)) {
                $column_names[] = $name;
            }
            return $this->add_modifiers($this->wrap($column) . ' ' . ($column->full_type_definition ?? $this->get_type($column)), $blueprint, $column);
        })->all();
        $indexes = (new Collection($blueprint->get_state()->get_indexes()))->reject(fn($index): bool => str_starts_with('sqlite_', (string) $index->index))->map(fn($index) => $this->{'compile' . ucfirst($index->name)}($blueprint, $index))->all();
        [, $table_name] = $this->connection->get_schema_builder()->parse_schema_and_table($blueprint->get_table());
        $temp_table = $this->wrap_table($blueprint, '__temp__' . $this->connection->get_table_prefix());
        $table = $this->wrap_table($blueprint);
        $column_names = implode(', ', $column_names);
        $foreign_key_constraints_enabled = $this->connection->scalar($this->pragma('foreign_keys'));
        return array_filter(array_merge([$foreign_key_constraints_enabled ? $this->compile_disable_foreign_key_constraints() : null, sprintf('create table %s (%s%s%s)', $temp_table, implode(', ', $columns), $this->add_foreign_keys($blueprint->get_state()->get_foreign_keys()), $auto_increment_column ? '' : $this->add_primary_keys($blueprint->get_state()->get_primary_key())), sprintf('insert into %s (%s) select %s from %s', $temp_table, $column_names, $column_names, $table), sprintf('drop table %s', $table), sprintf('alter table %s rename to %s', $temp_table, $this->wrap_table($table_name))], $indexes, [$foreign_key_constraints_enabled ? $this->compile_enable_foreign_key_constraints() : null]));
    }
    /** @inheritDoc */
    public function compile_change(Blueprint $blueprint, Fluent $command): void
    {
        // Handled on table alteration...
    }
    /**
     * Compile a primary key command.
     */
    public function compile_primary(Blueprint $blueprint, Fluent $command): void
    {
        // Handled on table creation or alteration...
    }
    /**
     * Compile a unique key command.
     */
    public function compile_unique(Blueprint $blueprint, Fluent $command): string
    {
        [$schema, $table] = $this->connection->get_schema_builder()->parse_schema_and_table($blueprint->get_table());
        return sprintf('create unique index %s%s on %s (%s)', $schema ? $this->wrap_value($schema) . '.' : '', $this->wrap($command->index), $this->wrap_table($table), $this->columnize($command->columns));
    }
    /**
     * Compile a plain index key command.
     */
    public function compile_index(Blueprint $blueprint, Fluent $command): string
    {
        [$schema, $table] = $this->connection->get_schema_builder()->parse_schema_and_table($blueprint->get_table());
        return sprintf('create index %s%s on %s (%s)', $schema ? $this->wrap_value($schema) . '.' : '', $this->wrap($command->index), $this->wrap_table($table), $this->columnize($command->columns));
    }
    /**
     * Compile a spatial index key command.
     *
     *
     * @throws \RuntimeException
     */
    public function compile_spatial_index(Blueprint $blueprint, Fluent $command): never
    {
        throw new RuntimeException('The database driver in use does not support spatial indexes.');
    }
    /**
     * Compile a foreign key command.
     */
    public function compile_foreign(Blueprint $blueprint, Fluent $command): void
    {
        // Handled on table creation or alteration...
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
     * Compile the SQL needed to drop all tables.
     *
     * @param  string|null  $schema
     */
    public function compile_drop_all_tables($schema = null): string
    {
        return sprintf("delete from %s.sqlite_master where type in ('table', 'index', 'trigger')", $this->wrap_value($schema ?? 'main'));
    }
    /**
     * Compile the SQL needed to drop all views.
     *
     * @param  string|null  $schema
     */
    public function compile_drop_all_views($schema = null): string
    {
        return sprintf("delete from %s.sqlite_master where type in ('view')", $this->wrap_value($schema ?? 'main'));
    }
    /**
     * Compile the SQL needed to rebuild the database.
     *
     * @param  string|null  $schema
     */
    public function compile_rebuild($schema = null): string
    {
        return sprintf('vacuum %s', $this->wrap_value($schema ?? 'main'));
    }
    /**
     * Compile a drop column command.
     *
     * @return list<string>|null
     */
    public function compile_drop_column(Blueprint $blueprint, Fluent $command)
    {
        if (version_compare($this->connection->get_server_version(), '3.35', '<')) {
            // Handled on table alteration...
            return null;
        }
        $table = $this->wrap_table($blueprint);
        $columns = $this->prefix_array('drop column', $this->wrap_array($command->columns));
        return (new Collection($columns))->map(fn($column): string => 'alter table ' . $table . ' ' . $column)->all();
    }
    /**
     * Compile a drop primary key command.
     */
    public function compile_drop_primary(Blueprint $blueprint, Fluent $command): void
    {
        // Handled on table alteration...
    }
    /**
     * Compile a drop unique key command.
     */
    public function compile_drop_unique(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_drop_index($blueprint, $command);
    }
    /**
     * Compile a drop index command.
     */
    public function compile_drop_index(Blueprint $blueprint, Fluent $command): string
    {
        [$schema] = $this->connection->get_schema_builder()->parse_schema_and_table($blueprint->get_table());
        return sprintf('drop index %s%s', $schema ? $this->wrap_value($schema) . '.' : '', $this->wrap($command->index));
    }
    /**
     * Compile a drop spatial index command.
     *
     *
     * @throws \RuntimeException
     */
    public function compile_drop_spatial_index(Blueprint $blueprint, Fluent $command): never
    {
        throw new RuntimeException('The database driver in use does not support spatial indexes.');
    }
    /**
     * Compile a drop foreign key command.
     */
    public function compile_drop_foreign(Blueprint $blueprint, Fluent $command): void
    {
        if (empty($command->columns)) {
            throw new RuntimeException('This database driver does not support dropping foreign keys by name.');
        }
        // Handled on table alteration...
    }
    /**
     * Compile a rename table command.
     */
    public function compile_rename(Blueprint $blueprint, Fluent $command): string
    {
        $from = $this->wrap_table($blueprint);
        return "alter table {$from} rename to " . $this->wrap_table($command->to);
    }
    /**
     * Compile a rename index command.
     *
     *
     * @throws \RuntimeException
     */
    public function compile_rename_index(Blueprint $blueprint, Fluent $command): array
    {
        $indexes = $this->connection->get_schema_builder()->get_indexes($blueprint->get_table());
        $index = Arr::first($indexes, fn($index): bool => $index['name'] === $command->from);
        if (!$index) {
            throw new RuntimeException("Index [{$command->from}] does not exist.");
        }
        if ($index['primary']) {
            throw new RuntimeException('SQLite does not support altering primary keys.');
        }
        if ($index['unique']) {
            return [$this->compile_drop_unique($blueprint, new Index_Definition(['index' => $index['name']])), $this->compile_unique($blueprint, new Index_Definition(['index' => $command->to, 'columns' => $index['columns']]))];
        }
        return [$this->compile_drop_index($blueprint, new Index_Definition(['index' => $index['name']])), $this->compile_index($blueprint, new Index_Definition(['index' => $command->to, 'columns' => $index['columns']]))];
    }
    /**
     * Compile the command to enable foreign key constraints.
     */
    public function compile_enable_foreign_key_constraints(): string
    {
        return $this->pragma('foreign_keys', 1);
    }
    /**
     * Compile the command to disable foreign key constraints.
     */
    public function compile_disable_foreign_key_constraints(): string
    {
        return $this->pragma('foreign_keys', 0);
    }
    /**
     * Get the SQL to get or set a PRAGMA value.
     */
    public function pragma(string $key, mixed $value = null): string
    {
        return sprintf('pragma %s%s', $key, is_null($value) ? '' : ' = ' . $value);
    }
    /**
     * Create the column definition for a char type.
     */
    protected function type_char(Fluent $column): string
    {
        return 'varchar';
    }
    /**
     * Create the column definition for a string type.
     */
    protected function type_string(Fluent $column): string
    {
        return 'varchar';
    }
    /**
     * Create the column definition for a tiny text type.
     */
    protected function type_tiny_text(Fluent $column): string
    {
        return 'text';
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
        return 'text';
    }
    /**
     * Create the column definition for a long text type.
     */
    protected function type_long_text(Fluent $column): string
    {
        return 'text';
    }
    /**
     * Create the column definition for an integer type.
     */
    protected function type_integer(Fluent $column): string
    {
        return 'integer';
    }
    /**
     * Create the column definition for a big integer type.
     */
    protected function type_big_integer(Fluent $column): string
    {
        return 'integer';
    }
    /**
     * Create the column definition for a medium integer type.
     */
    protected function type_medium_integer(Fluent $column): string
    {
        return 'integer';
    }
    /**
     * Create the column definition for a tiny integer type.
     */
    protected function type_tiny_integer(Fluent $column): string
    {
        return 'integer';
    }
    /**
     * Create the column definition for a small integer type.
     */
    protected function type_small_integer(Fluent $column): string
    {
        return 'integer';
    }
    /**
     * Create the column definition for a float type.
     */
    protected function type_float(Fluent $column): string
    {
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
        return 'numeric';
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
        return sprintf('varchar check ("%s" in (%s))', $column->name, $this->quote_string($column->allowed));
    }
    /**
     * Create the column definition for a json type.
     */
    protected function type_json(Fluent $column): string
    {
        return $this->connection->get_config('use_native_json') ? 'json' : 'text';
    }
    /**
     * Create the column definition for a jsonb type.
     */
    protected function type_jsonb(Fluent $column): string
    {
        return $this->connection->get_config('use_native_jsonb') ? 'jsonb' : 'text';
    }
    /**
     * Create the column definition for a date type.
     */
    protected function type_date(Fluent $column): string
    {
        if ($column->use_current) {
            $column->default(new Expression('CURRENT_DATE'));
        }
        return 'date';
    }
    /**
     * Create the column definition for a date-time type.
     */
    protected function type_date_time(Fluent $column): string
    {
        return $this->type_timestamp($column);
    }
    /**
     * Create the column definition for a date-time (with time zone) type.
     *
     * Note: "SQLite does not have a storage class set aside for storing dates and/or times."
     *
     * @link https://www.sqlite.org/datatype3.html
     *
     * @return string
     */
    protected function type_date_time_tz(Fluent $column)
    {
        return $this->type_date_time($column);
    }
    /**
     * Create the column definition for a time type.
     */
    protected function type_time(Fluent $column): string
    {
        return 'time';
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
        if ($column->use_current) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }
        return 'datetime';
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
        if ($column->use_current) {
            $column->default(new Expression("(CAST(strftime('%Y', 'now') AS INTEGER))"));
        }
        return $this->type_integer($column);
    }
    /**
     * Create the column definition for a binary type.
     */
    protected function type_binary(Fluent $column): string
    {
        return 'blob';
    }
    /**
     * Create the column definition for a uuid type.
     */
    protected function type_uuid(Fluent $column): string
    {
        return 'varchar';
    }
    /**
     * Create the column definition for an IP address type.
     */
    protected function type_ip_address(Fluent $column): string
    {
        return 'varchar';
    }
    /**
     * Create the column definition for a MAC address type.
     */
    protected function type_mac_address(Fluent $column): string
    {
        return 'varchar';
    }
    /**
     * Create the column definition for a spatial Geometry type.
     */
    protected function type_geometry(Fluent $column): string
    {
        return 'geometry';
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
            return " as ({$this->get_value($column->stored_as)}) stored";
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
            return $column->nullable ? '' : ' not null';
        }
        if ($column->nullable === false) {
            return ' not null';
        }
    }
    /**
     * Get the SQL for a default column modifier.
     *
     * @return string|null
     */
    protected function modify_default(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->default) && is_null($column->virtual_as) && is_null($column->virtual_as_json) && is_null($column->stored_as)) {
            return ' default ' . $this->get_default_value($column->default);
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
            return ' primary key autoincrement';
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
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrap_json_selector($value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($value);
        return 'json_extract(' . $field . $path . ')';
    }
}