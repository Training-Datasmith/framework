<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema\Grammars;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
class Sql_Server_Grammar extends Grammar
{
    /**
     * If this Grammar supports schema changes wrapped in a transaction.
     *
     * @var bool
     */
    protected $transactions = true;
    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected $modifiers = ['Collate', 'Nullable', 'Default', 'Persisted', 'Increment'];
    /**
     * The columns available as serials.
     *
     * @var string[]
     */
    protected $serials = ['tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger'];
    /**
     * The commands to be executed outside of create or alter command.
     *
     * @var string[]
     */
    protected $fluent_commands = ['Default'];
    /**
     * Compile the query to determine the schemas.
     */
    public function compile_schemas(): string
    {
        return 'select name, iif(schema_id = schema_id(), 1, 0) as [default] from sys.schemas ' . "where name not in ('information_schema', 'sys') and name not like 'db[_]%' order by name";
    }
    /**
     * Compile the query to determine if the given table exists.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_table_exists($schema, $table): void
    {
        return sprintf('select (case when object_id(%s, \'U\') is null then 0 else 1 end) as [exists]', $this->quote_string($schema ? $schema . '.' . $table : $table));
    }
    /**
     * Compile the query to determine the tables.
     *
     * @param  string|string[]|null  $schema
     */
    public function compile_tables($schema): string
    {
        return 'select t.name as name, schema_name(t.schema_id) as [schema], sum(u.total_pages) * 8 * 1024 as size ' . 'from sys.tables as t ' . 'join sys.partitions as p on p.object_id = t.object_id ' . 'join sys.allocation_units as u on u.container_id = p.hobt_id ' . "where t.is_ms_shipped = 0 and t.name <> 'sysdiagrams'" . $this->compile_schema_where_clause($schema, 'schema_name(t.schema_id)') . ' group by t.name, t.schema_id ' . 'order by [schema], t.name';
    }
    /**
     * Compile the query to determine the views.
     *
     * @param  string|string[]|null  $schema
     */
    public function compile_views($schema): string
    {
        return 'select name, schema_name(v.schema_id) as [schema], definition from sys.views as v ' . 'inner join sys.sql_modules as m on v.object_id = m.object_id ' . 'where v.is_ms_shipped = 0' . $this->compile_schema_where_clause($schema, 'schema_name(v.schema_id)') . ' order by [schema], name';
    }
    /**
     * Compile the query to compare the schema.
     *
     * @param  string|string[]|null  $schema
     * @param  string  $column
     */
    protected function compile_schema_where_clause($schema, $column): string
    {
        return match (true) {
            !empty($schema) && is_array($schema) => " and {$column} in (" . $this->quote_string($schema) . ')',
            !empty($schema) => " and {$column} = " . $this->quote_string($schema),
            default => '',
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
        return sprintf('select col.name, type.name as type_name, ' . 'col.max_length as length, col.precision as precision, col.scale as places, ' . 'col.is_nullable as nullable, def.definition as [default], ' . 'col.is_identity as autoincrement, col.collation_name as collation, ' . 'com.definition as [expression], is_persisted as [persisted], ' . 'cast(prop.value as nvarchar(max)) as comment ' . 'from sys.columns as col ' . 'join sys.types as type on col.user_type_id = type.user_type_id ' . 'join sys.objects as obj on col.object_id = obj.object_id ' . 'join sys.schemas as scm on obj.schema_id = scm.schema_id ' . 'left join sys.default_constraints def on col.default_object_id = def.object_id and col.object_id = def.parent_object_id ' . "left join sys.extended_properties as prop on obj.object_id = prop.major_id and col.column_id = prop.minor_id and prop.name = 'MS_Description' " . 'left join sys.computed_columns as com on col.column_id = com.column_id and col.object_id = com.object_id ' . "where obj.type in ('U', 'V') and obj.name = %s and scm.name = %s " . 'order by col.column_id', $this->quote_string($table), $schema ? $this->quote_string($schema) : 'schema_name()');
    }
    /**
     * Compile the query to determine the indexes.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_indexes($schema, $table): string
    {
        return sprintf("select idx.name as name, string_agg(col.name, ',') within group (order by idxcol.key_ordinal) as columns, " . 'idx.type_desc as [type], idx.is_unique as [unique], idx.is_primary_key as [primary] ' . 'from sys.indexes as idx ' . 'join sys.tables as tbl on idx.object_id = tbl.object_id ' . 'join sys.schemas as scm on tbl.schema_id = scm.schema_id ' . 'join sys.index_columns as idxcol on idx.object_id = idxcol.object_id and idx.index_id = idxcol.index_id ' . 'join sys.columns as col on idxcol.object_id = col.object_id and idxcol.column_id = col.column_id ' . 'where tbl.name = %s and scm.name = %s ' . 'group by idx.name, idx.type_desc, idx.is_unique, idx.is_primary_key', $this->quote_string($table), $schema ? $this->quote_string($schema) : 'schema_name()');
    }
    /**
     * Compile the query to determine the foreign keys.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_foreign_keys($schema, $table): string
    {
        return sprintf('select fk.name as name, ' . "string_agg(lc.name, ',') within group (order by fkc.constraint_column_id) as columns, " . 'fs.name as foreign_schema, ft.name as foreign_table, ' . "string_agg(fc.name, ',') within group (order by fkc.constraint_column_id) as foreign_columns, " . 'fk.update_referential_action_desc as on_update, ' . 'fk.delete_referential_action_desc as on_delete ' . 'from sys.foreign_keys as fk ' . 'join sys.foreign_key_columns as fkc on fkc.constraint_object_id = fk.object_id ' . 'join sys.tables as lt on lt.object_id = fk.parent_object_id ' . 'join sys.schemas as ls on lt.schema_id = ls.schema_id ' . 'join sys.columns as lc on fkc.parent_object_id = lc.object_id and fkc.parent_column_id = lc.column_id ' . 'join sys.tables as ft on ft.object_id = fk.referenced_object_id ' . 'join sys.schemas as fs on ft.schema_id = fs.schema_id ' . 'join sys.columns as fc on fkc.referenced_object_id = fc.object_id and fkc.referenced_column_id = fc.column_id ' . 'where lt.name = %s and ls.name = %s ' . 'group by fk.name, fs.name, ft.name, fk.update_referential_action_desc, fk.delete_referential_action_desc', $this->quote_string($table), $schema ? $this->quote_string($schema) : 'schema_name()');
    }
    /**
     * Compile a create table command.
     */
    public function compile_create(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('create table %s (%s)', $this->wrap_table($blueprint, $blueprint->temporary ? '#' . $this->connection->get_table_prefix() : null), implode(', ', $this->get_columns($blueprint)));
    }
    /**
     * Compile a column addition table command.
     */
    public function compile_add(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add %s', $this->wrap_table($blueprint), $this->get_column($blueprint, $command->column));
    }
    /** @inheritDoc */
    public function compile_rename_column(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf("sp_rename %s, %s, N'COLUMN'", $this->quote_string($this->wrap_table($blueprint) . '.' . $this->wrap($command->from)), $this->wrap($command->to));
    }
    /** @inheritDoc */
    public function compile_change(Blueprint $blueprint, Fluent $command): array
    {
        return [$this->compile_drop_default_constraint($blueprint, $command), sprintf('alter table %s alter column %s', $this->wrap_table($blueprint), $this->get_column($blueprint, $command->column))];
    }
    /**
     * Compile a primary key command.
     */
    public function compile_primary(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add constraint %s primary key (%s)', $this->wrap_table($blueprint), $this->wrap($command->index), $this->columnize($command->columns));
    }
    /**
     * Compile a unique key command.
     */
    public function compile_unique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('create unique index %s on %s (%s)%s', $this->wrap($command->index), $this->wrap_table($blueprint), $this->columnize($command->columns), $command->online ? ' with (online = on)' : '');
    }
    /**
     * Compile a plain index key command.
     */
    public function compile_index(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('create index %s on %s (%s)%s', $this->wrap($command->index), $this->wrap_table($blueprint), $this->columnize($command->columns), $command->online ? ' with (online = on)' : '');
    }
    /**
     * Compile a spatial index key command.
     */
    public function compile_spatial_index(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('create spatial index %s on %s (%s)', $this->wrap($command->index), $this->wrap_table($blueprint), $this->columnize($command->columns));
    }
    /**
     * Compile a default command.
     *
     * @return string|null
     */
    public function compile_default(Blueprint $blueprint, Fluent $command)
    {
        if ($command->column->change && !is_null($command->column->default)) {
            return sprintf('alter table %s add default %s for %s', $this->wrap_table($blueprint), $this->get_default_value($command->column->default), $this->wrap($command->column));
        }
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
        return sprintf('if object_id(%s, \'U\') is not null drop table %s', $this->quote_string($this->wrap_table($blueprint)), $this->wrap_table($blueprint));
    }
    /**
     * Compile the SQL needed to drop all tables.
     */
    public function compile_drop_all_tables(): string
    {
        return "EXEC sp_msforeachtable 'DROP TABLE ?'";
    }
    /**
     * Compile a drop column command.
     */
    public function compile_drop_column(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->wrap_array($command->columns);
        $drop_existing_constraints_sql = $this->compile_drop_default_constraint($blueprint, $command) . ';';
        return $drop_existing_constraints_sql . 'alter table ' . $this->wrap_table($blueprint) . ' drop column ' . implode(', ', $columns);
    }
    /**
     * Compile a drop default constraint command.
     */
    public function compile_drop_default_constraint(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $command->name === 'change' ? "'" . $command->column->name . "'" : "'" . implode("', '", $command->columns) . "'";
        $table = $this->wrap_table($blueprint);
        $table_name = $this->quote_string($this->wrap_table($blueprint));
        $sql = "DECLARE @sql NVARCHAR(MAX) = '';";
        $sql .= "SELECT @sql += 'ALTER TABLE {$table} DROP CONSTRAINT ' + OBJECT_NAME([default_object_id]) + ';' ";
        $sql .= 'FROM sys.columns ';
        $sql .= "WHERE [object_id] = OBJECT_ID({$table_name}) AND [name] in ({$columns}) AND [default_object_id] <> 0;";
        return $sql . 'EXEC(@sql)';
    }
    /**
     * Compile a drop primary key command.
     */
    public function compile_drop_primary(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);
        return "alter table {$this->wrap_table($blueprint)} drop constraint {$index}";
    }
    /**
     * Compile a drop unique key command.
     */
    public function compile_drop_unique(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);
        return "drop index {$index} on {$this->wrap_table($blueprint)}";
    }
    /**
     * Compile a drop index command.
     */
    public function compile_drop_index(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);
        return "drop index {$index} on {$this->wrap_table($blueprint)}";
    }
    /**
     * Compile a drop spatial index command.
     */
    public function compile_drop_spatial_index(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_drop_index($blueprint, $command);
    }
    /**
     * Compile a drop foreign key command.
     */
    public function compile_drop_foreign(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);
        return "alter table {$this->wrap_table($blueprint)} drop constraint {$index}";
    }
    /**
     * Compile a rename table command.
     */
    public function compile_rename(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('sp_rename %s, %s', $this->quote_string($this->wrap_table($blueprint)), $this->wrap_table($command->to));
    }
    /**
     * Compile a rename index command.
     */
    public function compile_rename_index(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf("sp_rename %s, %s, N'INDEX'", $this->quote_string($this->wrap_table($blueprint) . '.' . $this->wrap($command->from)), $this->wrap($command->to));
    }
    /**
     * Compile the command to enable foreign key constraints.
     */
    public function compile_enable_foreign_key_constraints(): string
    {
        return 'EXEC sp_msforeachtable @command1="print \'?\'", @command2="ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all";';
    }
    /**
     * Compile the command to disable foreign key constraints.
     */
    public function compile_disable_foreign_key_constraints(): string
    {
        return 'EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all";';
    }
    /**
     * Compile the command to drop all foreign keys.
     */
    public function compile_drop_all_foreign_keys(): string
    {
        return "DECLARE @sql NVARCHAR(MAX) = N'';\n            SELECT @sql += 'ALTER TABLE '\n                + QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id)) + '.' + + QUOTENAME(OBJECT_NAME(parent_object_id))\n                + ' DROP CONSTRAINT ' + QUOTENAME(name) + ';'\n            FROM sys.foreign_keys;\n\n            EXEC sp_executesql @sql;";
    }
    /**
     * Compile the command to drop all views.
     */
    public function compile_drop_all_views(): string
    {
        return "DECLARE @sql NVARCHAR(MAX) = N'';\n            SELECT @sql += 'DROP VIEW ' + QUOTENAME(OBJECT_SCHEMA_NAME(object_id)) + '.' + QUOTENAME(name) + ';'\n            FROM sys.views;\n\n            EXEC sp_executesql @sql;";
    }
    /**
     * Create the column definition for a char type.
     */
    protected function type_char(Fluent $column): string
    {
        return "nchar({$column->length})";
    }
    /**
     * Create the column definition for a string type.
     */
    protected function type_string(Fluent $column): string
    {
        return "nvarchar({$column->length})";
    }
    /**
     * Create the column definition for a tiny text type.
     */
    protected function type_tiny_text(Fluent $column): string
    {
        return 'nvarchar(255)';
    }
    /**
     * Create the column definition for a text type.
     */
    protected function type_text(Fluent $column): string
    {
        return 'nvarchar(max)';
    }
    /**
     * Create the column definition for a medium text type.
     */
    protected function type_medium_text(Fluent $column): string
    {
        return 'nvarchar(max)';
    }
    /**
     * Create the column definition for a long text type.
     */
    protected function type_long_text(Fluent $column): string
    {
        return 'nvarchar(max)';
    }
    /**
     * Create the column definition for an integer type.
     */
    protected function type_integer(Fluent $column): string
    {
        return 'int';
    }
    /**
     * Create the column definition for a big integer type.
     */
    protected function type_big_integer(Fluent $column): string
    {
        return 'bigint';
    }
    /**
     * Create the column definition for a medium integer type.
     */
    protected function type_medium_integer(Fluent $column): string
    {
        return 'int';
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
        return 'double precision';
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
        return 'bit';
    }
    /**
     * Create the column definition for an enumeration type.
     */
    protected function type_enum(Fluent $column): string
    {
        return sprintf('nvarchar(255) check ("%s" in (%s))', $column->name, $this->quote_string($column->allowed));
    }
    /**
     * Create the column definition for a json type.
     */
    protected function type_json(Fluent $column): string
    {
        return 'nvarchar(max)';
    }
    /**
     * Create the column definition for a jsonb type.
     */
    protected function type_jsonb(Fluent $column): string
    {
        return 'nvarchar(max)';
    }
    /**
     * Create the column definition for a date type.
     */
    protected function type_date(Fluent $column): string
    {
        if ($column->use_current) {
            $column->default(new Expression('CAST(GETDATE() AS DATE)'));
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
     */
    protected function type_date_time_tz(Fluent $column): string
    {
        return $this->type_timestamp_tz($column);
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
        if ($column->use_current) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }
        return $column->precision ? "datetime2({$column->precision})" : 'datetime';
    }
    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @link https://docs.microsoft.com/en-us/sql/t-sql/data-types/datetimeoffset-transact-sql?view=sql-server-ver15
     */
    protected function type_timestamp_tz(Fluent $column): string
    {
        if ($column->use_current) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }
        return $column->precision ? "datetimeoffset({$column->precision})" : 'datetimeoffset';
    }
    /**
     * Create the column definition for a year type.
     */
    protected function type_year(Fluent $column): string
    {
        if ($column->use_current) {
            $column->default(new Expression('CAST(YEAR(GETDATE()) AS INTEGER)'));
        }
        return $this->type_integer($column);
    }
    /**
     * Create the column definition for a binary type.
     */
    protected function type_binary(Fluent $column): string
    {
        if ($column->length) {
            return $column->fixed ? "binary({$column->length})" : "varbinary({$column->length})";
        }
        return 'varbinary(max)';
    }
    /**
     * Create the column definition for a uuid type.
     */
    protected function type_uuid(Fluent $column): string
    {
        return 'uniqueidentifier';
    }
    /**
     * Create the column definition for an IP address type.
     */
    protected function type_ip_address(Fluent $column): string
    {
        return 'nvarchar(45)';
    }
    /**
     * Create the column definition for a MAC address type.
     */
    protected function type_mac_address(Fluent $column): string
    {
        return 'nvarchar(17)';
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
        return 'geography';
    }
    /**
     * Create the column definition for a generated, computed column type.
     */
    protected function type_computed(Fluent $column): string
    {
        return "as ({$this->get_value($column->expression)})";
    }
    /**
     * Get the SQL for a collation column modifier.
     *
     * @return string|null
     */
    protected function modify_collate(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->collation)) {
            return ' collate ' . $column->collation;
        }
    }
    /**
     * Get the SQL for a nullable column modifier.
     *
     * @return string|null
     */
    protected function modify_nullable(Blueprint $blueprint, Fluent $column)
    {
        if ($column->type !== 'computed') {
            return $column->nullable ? ' null' : ' not null';
        }
    }
    /**
     * Get the SQL for a default column modifier.
     *
     * @return string|null
     */
    protected function modify_default(Blueprint $blueprint, Fluent $column)
    {
        if (!$column->change && !is_null($column->default)) {
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
        if (!$column->change && in_array($column->type, $this->serials) && $column->auto_increment) {
            return $this->has_command($blueprint, 'primary') ? ' identity' : ' identity primary key';
        }
    }
    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @return string|null
     */
    protected function modify_persisted(Blueprint $blueprint, Fluent $column)
    {
        if ($column->change) {
            if ($column->type === 'computed') {
                return $column->persisted ? ' add persisted' : ' drop persisted';
            }
            return null;
        }
        if ($column->persisted) {
            return ' persisted';
        }
    }
    /**
     * Quote the given string literal.
     *
     * @param  string|array<string>  $value
     */
    public function quote_string($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }
        return "N'{$value}'";
    }
}