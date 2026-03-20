<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema\Grammars;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use LogicException;
class Postgres_Grammar extends Grammar
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
    protected $modifiers = ['Collate', 'Nullable', 'Default', 'VirtualAs', 'StoredAs', 'GeneratedAs', 'Increment'];
    /**
     * The columns available as serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];
    /**
     * The commands to be executed outside of create or alter command.
     *
     * @var string[]
     */
    protected $fluent_commands = ['AutoIncrementStartingValues', 'Comment'];
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
            $sql .= sprintf(' encoding %s', $this->wrap_value($charset));
        }
        return $sql;
    }
    /**
     * Compile the query to determine the schemas.
     */
    public function compile_schemas(): string
    {
        return 'select nspname as name, nspname = current_schema() as "default" from pg_namespace where ' . $this->compile_schema_where_clause(null, 'nspname') . ' order by nspname';
    }
    /**
     * Compile the query to determine if the given table exists.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_table_exists($schema, $table): string
    {
        return sprintf('select exists (select 1 from pg_class c, pg_namespace n where ' . "n.nspname = %s and c.relname = %s and c.relkind in ('r', 'p') and n.oid = c.relnamespace)", $schema ? $this->quote_string($schema) : 'current_schema()', $this->quote_string($table));
    }
    /**
     * Compile the query to determine the tables.
     *
     * @param  string|string[]|null  $schema
     */
    public function compile_tables($schema): string
    {
        return 'select c.relname as name, n.nspname as schema, pg_total_relation_size(c.oid) as size, ' . "obj_description(c.oid, 'pg_class') as comment from pg_class c, pg_namespace n " . "where c.relkind in ('r', 'p') and n.oid = c.relnamespace and " . $this->compile_schema_where_clause($schema, 'n.nspname') . ' order by n.nspname, c.relname';
    }
    /**
     * Compile the query to determine the views.
     *
     * @param  string|string[]|null  $schema
     */
    public function compile_views($schema): string
    {
        return 'select viewname as name, schemaname as schema, definition from pg_views where ' . $this->compile_schema_where_clause($schema, 'schemaname') . ' order by schemaname, viewname';
    }
    /**
     * Compile the query to determine the user-defined types.
     *
     * @param  string|string[]|null  $schema
     */
    public function compile_types($schema): string
    {
        return 'select t.typname as name, n.nspname as schema, t.typtype as type, t.typcategory as category, ' . "((t.typinput = 'array_in'::regproc and t.typoutput = 'array_out'::regproc) or t.typtype = 'm') as implicit " . 'from pg_type t join pg_namespace n on n.oid = t.typnamespace ' . 'left join pg_class c on c.oid = t.typrelid ' . 'left join pg_type el on el.oid = t.typelem ' . 'left join pg_class ce on ce.oid = el.typrelid ' . "where ((t.typrelid = 0 and (ce.relkind = 'c' or ce.relkind is null)) or c.relkind = 'c') " . "and not exists (select 1 from pg_depend d where d.objid in (t.oid, t.typelem) and d.deptype = 'e') and " . $this->compile_schema_where_clause($schema, 'n.nspname');
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
            default => " <> 'information_schema' and {$column} not like 'pg\\_%'",
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
        return sprintf('select a.attname as name, t.typname as type_name, format_type(a.atttypid, a.atttypmod) as type, ' . '(select tc.collcollate from pg_catalog.pg_collation tc where tc.oid = a.attcollation) as collation, ' . 'not a.attnotnull as nullable, ' . '(select pg_get_expr(adbin, adrelid) from pg_attrdef where c.oid = pg_attrdef.adrelid and pg_attrdef.adnum = a.attnum) as default, ' . (version_compare($this->connection->get_server_version(), '12.0', '<') ? "'' as generated, " : 'a.attgenerated as generated, ') . 'col_description(c.oid, a.attnum) as comment ' . 'from pg_attribute a, pg_class c, pg_type t, pg_namespace n ' . 'where c.relname = %s and n.nspname = %s and a.attnum > 0 and a.attrelid = c.oid and a.atttypid = t.oid and n.oid = c.relnamespace ' . 'order by a.attnum', $this->quote_string($table), $schema ? $this->quote_string($schema) : 'current_schema()');
    }
    /**
     * Compile the query to determine the indexes.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_indexes($schema, $table): string
    {
        return sprintf("select ic.relname as name, string_agg(a.attname, ',' order by indseq.ord) as columns, " . 'am.amname as "type", i.indisunique as "unique", i.indisprimary as "primary" ' . 'from pg_index i ' . 'join pg_class tc on tc.oid = i.indrelid ' . 'join pg_namespace tn on tn.oid = tc.relnamespace ' . 'join pg_class ic on ic.oid = i.indexrelid ' . 'join pg_am am on am.oid = ic.relam ' . 'join lateral unnest(i.indkey) with ordinality as indseq(num, ord) on true ' . 'left join pg_attribute a on a.attrelid = i.indrelid and a.attnum = indseq.num ' . 'where tc.relname = %s and tn.nspname = %s ' . 'group by ic.relname, am.amname, i.indisunique, i.indisprimary', $this->quote_string($table), $schema ? $this->quote_string($schema) : 'current_schema()');
    }
    /**
     * Compile the query to determine the foreign keys.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compile_foreign_keys($schema, $table): string
    {
        return sprintf('select c.conname as name, ' . "string_agg(la.attname, ',' order by conseq.ord) as columns, " . 'fn.nspname as foreign_schema, fc.relname as foreign_table, ' . "string_agg(fa.attname, ',' order by conseq.ord) as foreign_columns, " . 'c.confupdtype as on_update, c.confdeltype as on_delete ' . 'from pg_constraint c ' . 'join pg_class tc on c.conrelid = tc.oid ' . 'join pg_namespace tn on tn.oid = tc.relnamespace ' . 'join pg_class fc on c.confrelid = fc.oid ' . 'join pg_namespace fn on fn.oid = fc.relnamespace ' . 'join lateral unnest(c.conkey) with ordinality as conseq(num, ord) on true ' . 'join pg_attribute la on la.attrelid = c.conrelid and la.attnum = conseq.num ' . 'join pg_attribute fa on fa.attrelid = c.confrelid and fa.attnum = c.confkey[conseq.ord] ' . "where c.contype = 'f' and tc.relname = %s and tn.nspname = %s " . 'group by c.conname, fn.nspname, fc.relname, c.confupdtype, c.confdeltype', $this->quote_string($table), $schema ? $this->quote_string($schema) : 'current_schema()');
    }
    /**
     * Compile a create table command.
     */
    public function compile_create(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('%s table %s (%s)', $blueprint->temporary ? 'create temporary' : 'create', $this->wrap_table($blueprint), implode(', ', $this->get_columns($blueprint)));
    }
    /**
     * Compile a column addition command.
     */
    public function compile_add(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add column %s', $this->wrap_table($blueprint), $this->get_column($blueprint, $command->column));
    }
    /**
     * Compile the auto-incrementing column starting values.
     *
     * @return string
     */
    public function compile_auto_increment_starting_values(Blueprint $blueprint, Fluent $command)
    {
        if ($command->column->auto_increment && $value = $command->column->get('startingValue', $command->column->get('from'))) {
            return sprintf('select setval(pg_get_serial_sequence(%s, %s), %s, false)', $this->quote_string($this->wrap_table($blueprint)), $this->quote_string($command->column->name), $value);
        }
    }
    /** @inheritDoc */
    public function compile_change(Blueprint $blueprint, Fluent $command): string
    {
        $column = $command->column;
        $changes = ['type ' . $this->get_type($column) . $this->modify_collate($blueprint, $column)];
        foreach ($this->modifiers as $modifier) {
            if ($modifier === 'Collate') {
                continue;
            }
            if (method_exists($this, $method = "modify{$modifier}")) {
                $constraints = (array) $this->{$method}($blueprint, $column);
                foreach ($constraints as $constraint) {
                    $changes[] = $constraint;
                }
            }
        }
        return sprintf('alter table %s %s', $this->wrap_table($blueprint), implode(', ', $this->prefix_array('alter column ' . $this->wrap($column), $changes)));
    }
    /**
     * Compile a primary key command.
     */
    public function compile_primary(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->columnize($command->columns);
        return 'alter table ' . $this->wrap_table($blueprint) . " add primary key ({$columns})";
    }
    /**
     * Compile a unique key command.
     *
     * @return string[]
     */
    public function compile_unique(Blueprint $blueprint, Fluent $command): array
    {
        $unique_statement = 'unique';
        if (!is_null($command->nulls_not_distinct)) {
            $unique_statement .= ' nulls ' . ($command->nulls_not_distinct ? 'not distinct' : 'distinct');
        }
        if ($command->online || $command->algorithm) {
            $create_index_sql = sprintf('create unique index %s%s on %s%s (%s)', $command->online ? 'concurrently ' : '', $this->wrap($command->index), $this->wrap_table($blueprint), $command->algorithm ? ' using ' . $command->algorithm : '', $this->columnize($command->columns));
            $sql = sprintf('alter table %s add constraint %s unique using index %s', $this->wrap_table($blueprint), $this->wrap($command->index), $this->wrap($command->index));
        } else {
            $sql = sprintf('alter table %s add constraint %s %s (%s)', $this->wrap_table($blueprint), $this->wrap($command->index), $unique_statement, $this->columnize($command->columns));
        }
        if (!is_null($command->deferrable)) {
            $sql .= $command->deferrable ? ' deferrable' : ' not deferrable';
        }
        if ($command->deferrable && !is_null($command->initially_immediate)) {
            $sql .= $command->initially_immediate ? ' initially immediate' : ' initially deferred';
        }
        return isset($create_index_sql) ? [$create_index_sql, $sql] : [$sql];
    }
    /**
     * Compile a plain index key command.
     */
    public function compile_index(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('create index %s%s on %s%s (%s)', $command->online ? 'concurrently ' : '', $this->wrap($command->index), $this->wrap_table($blueprint), $command->algorithm ? ' using ' . $command->algorithm : '', $this->columnize($command->columns));
    }
    /**
     * Compile a fulltext index key command.
     *
     *
     * @throws \RuntimeException
     */
    public function compile_fulltext(Blueprint $blueprint, Fluent $command): string
    {
        $language = $command->language ?: 'english';
        $columns = array_map(fn($column): string => "to_tsvector({$this->quote_string($language)}, {$this->wrap($column)})", $command->columns);
        return sprintf('create index %s%s on %s using gin ((%s))', $command->online ? 'concurrently ' : '', $this->wrap($command->index), $this->wrap_table($blueprint), implode(' || ', $columns));
    }
    /**
     * Compile a spatial index key command.
     */
    public function compile_spatial_index(Blueprint $blueprint, Fluent $command): string
    {
        $command->algorithm = 'gist';
        if (!is_null($command->operator_class)) {
            return $this->compile_index_with_operator_class($blueprint, $command);
        }
        return $this->compile_index($blueprint, $command);
    }
    /**
     * Compile a vector index key command.
     */
    public function compile_vector_index(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compile_index_with_operator_class($blueprint, $command);
    }
    /**
     * Compile a spatial index with operator class key command.
     */
    protected function compile_index_with_operator_class(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->columnize_with_operator_class($command->columns, $command->operator_class);
        return sprintf('create index %s%s on %s%s (%s)', $command->online ? 'concurrently ' : '', $this->wrap($command->index), $this->wrap_table($blueprint), $command->algorithm ? ' using ' . $command->algorithm : '', $columns);
    }
    /**
     * Convert an array of column names to a delimited string with operator class.
     *
     * @param  string  $operatorClass
     */
    protected function columnize_with_operator_class(array $columns, $operator_class): string
    {
        return implode(', ', array_map(fn($column): string => $this->wrap($column) . ' ' . $operator_class, $columns));
    }
    /**
     * Compile a foreign key command.
     *
     * @return string
     */
    public function compile_foreign(Blueprint $blueprint, Fluent $command)
    {
        $sql = parent::compile_foreign($blueprint, $command);
        if (!is_null($command->deferrable)) {
            $sql .= $command->deferrable ? ' deferrable' : ' not deferrable';
        }
        if ($command->deferrable && !is_null($command->initially_immediate)) {
            $sql .= $command->initially_immediate ? ' initially immediate' : ' initially deferred';
        }
        if (!is_null($command->not_valid)) {
            $sql .= ' not valid';
        }
        return $sql;
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
     * @param  array<string>  $tables
     */
    public function compile_drop_all_tables($tables): string
    {
        return 'drop table ' . implode(', ', $this->escape_names($tables)) . ' cascade';
    }
    /**
     * Compile the SQL needed to drop all views.
     *
     * @param  array<string>  $views
     */
    public function compile_drop_all_views($views): string
    {
        return 'drop view ' . implode(', ', $this->escape_names($views)) . ' cascade';
    }
    /**
     * Compile the SQL needed to drop all types.
     *
     * @param  array<string>  $types
     */
    public function compile_drop_all_types($types): string
    {
        return 'drop type ' . implode(', ', $this->escape_names($types)) . ' cascade';
    }
    /**
     * Compile the SQL needed to drop all domains.
     *
     * @param  array<string>  $domains
     */
    public function compile_drop_all_domains($domains): string
    {
        return 'drop domain ' . implode(', ', $this->escape_names($domains)) . ' cascade';
    }
    /**
     * Compile a drop column command.
     */
    public function compile_drop_column(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->prefix_array('drop column', $this->wrap_array($command->columns));
        return 'alter table ' . $this->wrap_table($blueprint) . ' ' . implode(', ', $columns);
    }
    /**
     * Compile a drop primary key command.
     */
    public function compile_drop_primary(Blueprint $blueprint, Fluent $command): string
    {
        [, $table] = $this->connection->get_schema_builder()->parse_schema_and_table($blueprint->get_table());
        $index = $this->wrap("{$this->connection->get_table_prefix()}{$table}_pkey");
        return 'alter table ' . $this->wrap_table($blueprint) . " drop constraint {$index}";
    }
    /**
     * Compile a drop unique key command.
     */
    public function compile_drop_unique(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);
        return "alter table {$this->wrap_table($blueprint)} drop constraint {$index}";
    }
    /**
     * Compile a drop index command.
     */
    public function compile_drop_index(Blueprint $blueprint, Fluent $command): string
    {
        return "drop index {$this->wrap($command->index)}";
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
        $from = $this->wrap_table($blueprint);
        return "alter table {$from} rename to " . $this->wrap_table($command->to);
    }
    /**
     * Compile a rename index command.
     */
    public function compile_rename_index(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter index %s rename to %s', $this->wrap($command->from), $this->wrap($command->to));
    }
    /**
     * Compile the command to enable foreign key constraints.
     */
    public function compile_enable_foreign_key_constraints(): string
    {
        return 'SET CONSTRAINTS ALL IMMEDIATE;';
    }
    /**
     * Compile the command to disable foreign key constraints.
     */
    public function compile_disable_foreign_key_constraints(): string
    {
        return 'SET CONSTRAINTS ALL DEFERRED;';
    }
    /**
     * Compile a comment command.
     *
     * @return string
     */
    public function compile_comment(Blueprint $blueprint, Fluent $command)
    {
        if (!is_null($comment = $command->column->comment) || $command->column->change) {
            return sprintf('comment on column %s.%s is %s', $this->wrap_table($blueprint), $this->wrap($command->column->name), is_null($comment) ? 'NULL' : "'" . str_replace("'", "''", $comment) . "'");
        }
    }
    /**
     * Compile a table comment command.
     */
    public function compile_table_comment(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('comment on table %s is %s', $this->wrap_table($blueprint), "'" . str_replace("'", "''", $command->comment) . "'");
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
        if ($column->length) {
            return "char({$column->length})";
        }
        return 'char';
    }
    /**
     * Create the column definition for a string type.
     */
    protected function type_string(Fluent $column): string
    {
        if ($column->length) {
            return "varchar({$column->length})";
        }
        return 'varchar';
    }
    /**
     * Create the column definition for a tiny text type.
     */
    protected function type_tiny_text(Fluent $column): string
    {
        return 'varchar(255)';
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
        return $column->auto_increment && is_null($column->generated_as) && !$column->change ? 'serial' : 'integer';
    }
    /**
     * Create the column definition for a big integer type.
     */
    protected function type_big_integer(Fluent $column): string
    {
        return $column->auto_increment && is_null($column->generated_as) && !$column->change ? 'bigserial' : 'bigint';
    }
    /**
     * Create the column definition for a medium integer type.
     */
    protected function type_medium_integer(Fluent $column): string
    {
        return $this->type_integer($column);
    }
    /**
     * Create the column definition for a tiny integer type.
     */
    protected function type_tiny_integer(Fluent $column): string
    {
        return $this->type_small_integer($column);
    }
    /**
     * Create the column definition for a small integer type.
     */
    protected function type_small_integer(Fluent $column): string
    {
        return $column->auto_increment && is_null($column->generated_as) && !$column->change ? 'smallserial' : 'smallint';
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
     * Create the column definition for a real type.
     */
    protected function type_real(Fluent $column): string
    {
        return 'real';
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
        return 'boolean';
    }
    /**
     * Create the column definition for an enumeration type.
     */
    protected function type_enum(Fluent $column): string
    {
        return sprintf('varchar(255) check ("%s" in (%s))', $column->name, $this->quote_string($column->allowed));
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
        return 'jsonb';
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
        return 'time' . (is_null($column->precision) ? '' : "({$column->precision})") . ' without time zone';
    }
    /**
     * Create the column definition for a time (with time zone) type.
     */
    protected function type_time_tz(Fluent $column): string
    {
        return 'time' . (is_null($column->precision) ? '' : "({$column->precision})") . ' with time zone';
    }
    /**
     * Create the column definition for a timestamp type.
     */
    protected function type_timestamp(Fluent $column): string
    {
        if ($column->use_current) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }
        return 'timestamp' . (is_null($column->precision) ? '' : "({$column->precision})") . ' without time zone';
    }
    /**
     * Create the column definition for a timestamp (with time zone) type.
     */
    protected function type_timestamp_tz(Fluent $column): string
    {
        if ($column->use_current) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }
        return 'timestamp' . (is_null($column->precision) ? '' : "({$column->precision})") . ' with time zone';
    }
    /**
     * Create the column definition for a year type.
     */
    protected function type_year(Fluent $column): string
    {
        if ($column->use_current) {
            $column->default(new Expression('EXTRACT(YEAR FROM CURRENT_DATE)'));
        }
        return $this->type_integer($column);
    }
    /**
     * Create the column definition for a binary type.
     */
    protected function type_binary(Fluent $column): string
    {
        return 'bytea';
    }
    /**
     * Create the column definition for a uuid type.
     */
    protected function type_uuid(Fluent $column): string
    {
        return 'uuid';
    }
    /**
     * Create the column definition for an IP address type.
     */
    protected function type_ip_address(Fluent $column): string
    {
        return 'inet';
    }
    /**
     * Create the column definition for a MAC address type.
     */
    protected function type_mac_address(Fluent $column): string
    {
        return 'macaddr';
    }
    /**
     * Create the column definition for a spatial Geometry type.
     */
    protected function type_geometry(Fluent $column): string
    {
        if ($column->subtype) {
            return sprintf('geometry(%s%s)', strtolower($column->subtype), $column->srid ? ',' . $column->srid : '');
        }
        return 'geometry';
    }
    /**
     * Create the column definition for a spatial Geography type.
     */
    protected function type_geography(Fluent $column): string
    {
        if ($column->subtype) {
            return sprintf('geography(%s%s)', strtolower($column->subtype), $column->srid ? ',' . $column->srid : '');
        }
        return 'geography';
    }
    /**
     * Create the column definition for a vector type.
     */
    protected function type_vector(Fluent $column): string
    {
        return isset($column->dimensions) && $column->dimensions !== '' ? "vector({$column->dimensions})" : 'vector';
    }
    /**
     * Create the column definition for a tsvector type.
     */
    protected function type_tsvector(Fluent $column): string
    {
        return 'tsvector';
    }
    /**
     * Get the SQL for a collation column modifier.
     *
     * @return string|null
     */
    protected function modify_collate(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($column->collation)) {
            return ' collate ' . $this->wrap_value($column->collation);
        }
    }
    /**
     * Get the SQL for a nullable column modifier.
     */
    protected function modify_nullable(Blueprint $blueprint, Fluent $column): string
    {
        if ($column->change) {
            return $column->nullable ? 'drop not null' : 'set not null';
        }
        return $column->nullable ? ' null' : ' not null';
    }
    /**
     * Get the SQL for a default column modifier.
     *
     * @return string|null
     */
    protected function modify_default(Blueprint $blueprint, Fluent $column)
    {
        if ($column->change) {
            if (!$column->auto_increment || !is_null($column->generated_as)) {
                return is_null($column->default) ? 'drop default' : 'set default ' . $this->get_default_value($column->default);
            }
            return null;
        }
        if (!is_null($column->default)) {
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
        if (!$column->change && !$this->has_command($blueprint, 'primary') && (in_array($column->type, $this->serials) || $column->generated_as !== null) && $column->auto_increment) {
            return ' primary key';
        }
    }
    /**
     * Get the SQL for a generated virtual column modifier.
     *
     * @return string|null
     */
    protected function modify_virtual_as(Blueprint $blueprint, Fluent $column)
    {
        if ($column->change) {
            if (array_key_exists('virtualAs', $column->get_attributes())) {
                return is_null($column->virtual_as) ? 'drop expression if exists' : throw new LogicException('This database driver does not support modifying generated columns.');
            }
            return null;
        }
        if (!is_null($column->virtual_as)) {
            return " generated always as ({$this->get_value($column->virtual_as)}) virtual";
        }
    }
    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @return string|null
     */
    protected function modify_stored_as(Blueprint $blueprint, Fluent $column)
    {
        if ($column->change) {
            if (array_key_exists('storedAs', $column->get_attributes())) {
                return is_null($column->stored_as) ? 'drop expression if exists' : throw new LogicException('This database driver does not support modifying generated columns.');
            }
            return null;
        }
        if (!is_null($column->stored_as)) {
            return " generated always as ({$this->get_value($column->stored_as)}) stored";
        }
    }
    /**
     * Get the SQL for an identity column modifier.
     *
     * @return string|list<string>|null
     */
    protected function modify_generated_as(Blueprint $blueprint, Fluent $column): array|string|null
    {
        $sql = null;
        if (!is_null($column->generated_as)) {
            $sql = sprintf(' generated %s as identity%s', $column->always ? 'always' : 'by default', !is_bool($column->generated_as) && !empty($column->generated_as) ? " ({$column->generated_as})" : '');
        }
        if ($column->change) {
            $changes = $column->auto_increment && is_null($sql) ? [] : ['drop identity if exists'];
            if (!is_null($sql)) {
                $changes[] = 'add ' . $sql;
            }
            return $changes;
        }
        return $sql;
    }
}