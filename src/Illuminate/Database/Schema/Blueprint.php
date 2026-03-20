<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Concerns\Has_Ulids;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Database\Schema\Grammars\My_Sql_Grammar;
use Illuminate\Database\Schema\Grammars\Sq_Lite_Grammar;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Fluent;
use Illuminate\Support\Traits\Macroable;
class Blueprint
{
    use Macroable;
    /**
     * The schema grammar instance.
     */
    protected Grammar $grammar;
    /**
     * The columns that should be added to the table.
     *
     * @var \Illuminate\Database\Schema\ColumnDefinition[]
     */
    protected $columns = [];
    /**
     * The commands that should be run for the table.
     *
     * @var \Illuminate\Support\Fluent[]
     */
    protected $commands = [];
    /**
     * The storage engine that should be used for the table.
     *
     * @var string
     */
    public $engine;
    /**
     * The default character set that should be used for the table.
     *
     * @var string
     */
    public $charset;
    /**
     * The collation that should be used for the table.
     *
     * @var string
     */
    public $collation;
    /**
     * Whether to make the table temporary.
     *
     * @var bool
     */
    public $temporary = false;
    /**
     * The column to add new columns after.
     *
     * @var string
     */
    public $after;
    /**
     * The blueprint state instance.
     *
     * @var \Illuminate\Database\Schema\BlueprintState|null
     */
    protected $state;
    /**
     * Create a new schema blueprint.
     *
     * @param  string  $table
     * @param  (\Closure(self): void)|null  $callback
     */
    public function __construct(
        protected Connection $connection,
        /**
         * The table the blueprint describes.
         */
        protected $table,
        ?Closure $callback = null
    )
    {
        $this->grammar = $this->connection->get_schema_grammar();
        if (!is_null($callback)) {
            $callback($this);
        }
    }
    /**
     * Execute the blueprint against the database.
     */
    public function build(): void
    {
        foreach ($this->to_sql() as $statement) {
            $this->connection->statement($statement);
        }
    }
    /**
     * Get the raw SQL statements for the blueprint.
     */
    public function to_sql(): array
    {
        $this->add_implied_commands();
        $statements = [];
        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        $this->ensure_commands_are_valid();
        foreach ($this->commands as $command) {
            if ($command->should_be_skipped) {
                continue;
            }
            $method = 'compile' . ucfirst((string) $command->name);
            if (method_exists($this->grammar, $method) || $this->grammar::has_macro($method)) {
                if ($this->has_state()) {
                    $this->state->update($command);
                }
                if (!is_null($sql = $this->grammar->{$method}($this, $command))) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }
        return $statements;
    }
    /**
     * Ensure the commands on the blueprint are valid for the connection type.
     *
     * @return void
     *
     * @throws \BadMethodCallException
     */
    protected function ensure_commands_are_valid()
    {
    }
    /**
     * Get all of the commands matching the given names.
     *
     * @deprecated Will be removed in a future Laravel version.
     */
    protected function commands_named(array $names): \Illuminate\Support\Collection
    {
        return (new Collection($this->commands))->filter(fn($command): bool => in_array($command->name, $names));
    }
    /**
     * Add the commands that are implied by the blueprint's state.
     *
     * @return void
     */
    protected function add_implied_commands()
    {
        $this->add_fluent_indexes();
        $this->add_fluent_commands();
        if (!$this->creating()) {
            $this->commands = array_map(fn(\Illuminate\Support\Fluent $command): \Illuminate\Support\Fluent => $command instanceof Column_Definition ? $this->create_command($command->change ? 'change' : 'add', ['column' => $command]) : $command, $this->commands);
            $this->add_alter_commands();
        }
    }
    /**
     * Add the index commands fluently specified on columns.
     *
     * @return void
     */
    protected function add_fluent_indexes()
    {
        foreach ($this->columns as $column) {
            foreach (['primary', 'unique', 'index', 'fulltext', 'fullText', 'spatialIndex', 'vectorIndex'] as $index) {
                // If the column is supposed to be changed to an auto increment column and
                // the specified index is primary, there is no need to add a command on
                // MySQL, as it will be handled during the column definition instead.
                if ($index === 'primary' && $column->auto_increment && $column->change && $this->grammar instanceof My_Sql_Grammar) {
                    continue 2;
                }
                // If the index has been specified on the given column, but is simply equal
                // to "true" (boolean), no name has been specified for this index so the
                // index method can be called without a name and it will generate one.
                if ($column->{$index} === true) {
                    $index_method = $index === 'index' && $column->type === 'vector' ? 'vectorIndex' : $index;
                    $this->{$index_method}($column->name);
                    $column->{$index} = null;
                    continue 2;
                }
                // If the index has been specified on the given column, but it equals false
                // and the column is supposed to be changed, we will call the drop index
                // method with an array of column to drop it by its conventional name.
                if ($column->{$index} === false && $column->change) {
                    $this->{'drop' . ucfirst($index)}([$column->name]);
                    $column->{$index} = null;
                    continue 2;
                }
                // If the index has been specified on the given column, and it has a string
                // value, we'll go ahead and call the index method and pass the name for
                // the index since the developer specified the explicit name for this.
                if (isset($column->{$index})) {
                    $index_method = $index === 'index' && $column->type === 'vector' ? 'vectorIndex' : $index;
                    $this->{$index_method}($column->name, $column->{$index});
                    $column->{$index} = null;
                    continue 2;
                }
            }
        }
    }
    /**
     * Add the fluent commands specified on any columns.
     */
    public function add_fluent_commands(): void
    {
        foreach ($this->columns as $column) {
            foreach ($this->grammar->get_fluent_commands() as $command_name) {
                $this->add_command($command_name, compact('column'));
            }
        }
    }
    /**
     * Add the alter commands if whenever needed.
     */
    public function add_alter_commands(): void
    {
        if (!$this->grammar instanceof Sq_Lite_Grammar) {
            return;
        }
        $alter_commands = $this->grammar->get_alter_commands();
        [$commands, $last_command_was_alter, $has_alter_command] = [[], false, false];
        foreach ($this->commands as $command) {
            if (in_array($command->name, $alter_commands)) {
                $has_alter_command = true;
                $last_command_was_alter = true;
            } elseif ($last_command_was_alter) {
                $commands[] = $this->create_command('alter');
                $last_command_was_alter = false;
            }
            $commands[] = $command;
        }
        if ($last_command_was_alter) {
            $commands[] = $this->create_command('alter');
        }
        if ($has_alter_command) {
            $this->state = new Blueprint_State($this, $this->connection);
        }
        $this->commands = $commands;
    }
    /**
     * Determine if the blueprint has a create command.
     *
     * @return bool
     */
    public function creating()
    {
        return (new Collection($this->commands))->contains(fn($command): bool => !$command instanceof Column_Definition && $command->name === 'create');
    }
    /**
     * Indicate that the table needs to be created.
     *
     * @return \Illuminate\Support\Fluent
     */
    public function create()
    {
        return $this->add_command('create');
    }
    /**
     * Specify the storage engine that should be used for the table.
     *
     * @param  string  $engine
     */
    public function engine($engine): void
    {
        $this->engine = $engine;
    }
    /**
     * Specify that the InnoDB storage engine should be used for the table (MySQL only).
     */
    public function inno_db(): void
    {
        $this->engine('InnoDB');
    }
    /**
     * Specify the character set that should be used for the table.
     *
     * @param  string  $charset
     */
    public function charset($charset): void
    {
        $this->charset = $charset;
    }
    /**
     * Specify the collation that should be used for the table.
     *
     * @param  string  $collation
     */
    public function collation($collation): void
    {
        $this->collation = $collation;
    }
    /**
     * Indicate that the table needs to be temporary.
     */
    public function temporary(): void
    {
        $this->temporary = true;
    }
    /**
     * Indicate that the table should be dropped.
     *
     * @return \Illuminate\Support\Fluent
     */
    public function drop()
    {
        return $this->add_command('drop');
    }
    /**
     * Indicate that the table should be dropped if it exists.
     *
     * @return \Illuminate\Support\Fluent
     */
    public function drop_if_exists()
    {
        return $this->add_command('dropIfExists');
    }
    /**
     * Indicate that the given columns should be dropped.
     *
     * @param  mixed  $columns
     * @return \Illuminate\Support\Fluent
     */
    public function drop_column($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        return $this->add_command('dropColumn', compact('columns'));
    }
    /**
     * Indicate that the given columns should be renamed.
     *
     * @param  string  $from
     * @param  string  $to
     * @return \Illuminate\Support\Fluent
     */
    public function rename_column($from, $to)
    {
        return $this->add_command('renameColumn', compact('from', 'to'));
    }
    /**
     * Indicate that the given primary key should be dropped.
     *
     * @param  string|array|null  $index
     * @return \Illuminate\Support\Fluent
     */
    public function drop_primary($index = null)
    {
        return $this->drop_index_command('dropPrimary', 'primary', $index);
    }
    /**
     * Indicate that the given unique key should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function drop_unique($index)
    {
        return $this->drop_index_command('dropUnique', 'unique', $index);
    }
    /**
     * Indicate that the given index should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function drop_index($index)
    {
        return $this->drop_index_command('dropIndex', 'index', $index);
    }
    /**
     * Indicate that the given fulltext index should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function drop_full_text($index)
    {
        return $this->drop_index_command('dropFullText', 'fulltext', $index);
    }
    /**
     * Indicate that the given spatial index should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function drop_spatial_index($index)
    {
        return $this->drop_index_command('dropSpatialIndex', 'spatialIndex', $index);
    }
    /**
     * Indicate that the given foreign key should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function drop_foreign($index)
    {
        return $this->drop_index_command('dropForeign', 'foreign', $index);
    }
    /**
     * Indicate that the given column and foreign key should be dropped.
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function drop_constrained_foreign_id($column)
    {
        $this->drop_foreign([$column]);
        return $this->drop_column($column);
    }
    /**
     * Indicate that the given foreign key should be dropped.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string|null  $column
     * @return \Illuminate\Support\Fluent
     */
    public function drop_foreign_id_for($model, $column = null)
    {
        if (is_string($model)) {
            $model = new $model();
        }
        return $this->drop_column($column ?: $model->get_foreign_key());
    }
    /**
     * Indicate that the given foreign key should be dropped.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string|null  $column
     * @return \Illuminate\Support\Fluent
     */
    public function drop_constrained_foreign_id_for($model, $column = null)
    {
        if (is_string($model)) {
            $model = new $model();
        }
        return $this->drop_constrained_foreign_id($column ?: $model->get_foreign_key());
    }
    /**
     * Indicate that the given indexes should be renamed.
     *
     * @param  string  $from
     * @param  string  $to
     * @return \Illuminate\Support\Fluent
     */
    public function rename_index($from, $to)
    {
        return $this->add_command('renameIndex', compact('from', 'to'));
    }
    /**
     * Indicate that the timestamp columns should be dropped.
     */
    public function drop_timestamps(): void
    {
        $this->drop_column('created_at', 'updated_at');
    }
    /**
     * Indicate that the timestamp columns should be dropped.
     */
    public function drop_timestamps_tz(): void
    {
        $this->drop_timestamps();
    }
    /**
     * Indicate that the soft delete column should be dropped.
     *
     * @param  string  $column
     */
    public function drop_soft_deletes($column = 'deleted_at'): void
    {
        $this->drop_column($column);
    }
    /**
     * Indicate that the soft delete column should be dropped.
     *
     * @param  string  $column
     */
    public function drop_soft_deletes_tz($column = 'deleted_at'): void
    {
        $this->drop_soft_deletes($column);
    }
    /**
     * Indicate that the remember token column should be dropped.
     */
    public function drop_remember_token(): void
    {
        $this->drop_column('remember_token');
    }
    /**
     * Indicate that the polymorphic columns should be dropped.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     */
    public function drop_morphs($name, $index_name = null): void
    {
        $this->drop_index($index_name ?: $this->create_index_name('index', ["{$name}_type", "{$name}_id"]));
        $this->drop_column("{$name}_type", "{$name}_id");
    }
    /**
     * Rename the table to a given name.
     *
     * @param  string  $to
     * @return \Illuminate\Support\Fluent
     */
    public function rename($to)
    {
        return $this->add_command('rename', compact('to'));
    }
    /**
     * Specify the primary key(s) for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     * @param  string|null  $algorithm
     * @return \Illuminate\Database\Schema\IndexDefinition
     */
    public function primary($columns, $name = null, $algorithm = null)
    {
        return $this->index_command('primary', $columns, $name, $algorithm);
    }
    /**
     * Specify a unique index for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     * @param  string|null  $algorithm
     * @return \Illuminate\Database\Schema\IndexDefinition
     */
    public function unique($columns, $name = null, $algorithm = null)
    {
        return $this->index_command('unique', $columns, $name, $algorithm);
    }
    /**
     * Specify an index for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     * @param  string|null  $algorithm
     * @return \Illuminate\Database\Schema\IndexDefinition
     */
    public function index($columns, $name = null, $algorithm = null)
    {
        return $this->index_command('index', $columns, $name, $algorithm);
    }
    /**
     * Specify a fulltext index for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     * @param  string|null  $algorithm
     * @return \Illuminate\Database\Schema\IndexDefinition
     */
    public function full_text($columns, $name = null, $algorithm = null)
    {
        return $this->index_command('fulltext', $columns, $name, $algorithm);
    }
    /**
     * Specify a spatial index for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     * @param  string|null  $operatorClass
     * @return \Illuminate\Database\Schema\IndexDefinition
     */
    public function spatial_index($columns, $name = null, $operator_class = null)
    {
        return $this->index_command('spatialIndex', $columns, $name, null, $operator_class);
    }
    /**
     * Specify a vector index for the table.
     *
     * @param  string  $column
     * @param  string|null  $name
     * @return \Illuminate\Database\Schema\IndexDefinition
     */
    public function vector_index($column, $name = null)
    {
        return $this->index_command('vectorIndex', $column, $name, 'hnsw', 'vector_cosine_ops');
    }
    /**
     * Specify a raw index for the table.
     *
     * @param  string  $expression
     * @param  string  $name
     * @return \Illuminate\Database\Schema\IndexDefinition
     */
    public function raw_index($expression, $name)
    {
        return $this->index([new Expression($expression)], $name);
    }
    /**
     * Specify a foreign key for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     */
    public function foreign($columns, $name = null): \Illuminate\Database\Schema\Foreign_Key_Definition
    {
        $command = new Foreign_Key_Definition($this->index_command('foreign', $columns, $name)->get_attributes());
        $this->commands[count($this->commands) - 1] = $command;
        return $command;
    }
    /**
     * Create a new auto-incrementing big integer column on the table (8-byte, 0 to 18,446,744,073,709,551,615).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function id($column = 'id')
    {
        return $this->big_increments($column);
    }
    /**
     * Create a new auto-incrementing integer column on the table (4-byte, 0 to 4,294,967,295).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function increments($column)
    {
        return $this->unsigned_integer($column, true);
    }
    /**
     * Create a new auto-incrementing integer column on the table (4-byte, 0 to 4,294,967,295).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function integer_increments($column)
    {
        return $this->unsigned_integer($column, true);
    }
    /**
     * Create a new auto-incrementing tiny integer column on the table (1-byte, 0 to 255).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function tiny_increments($column)
    {
        return $this->unsigned_tiny_integer($column, true);
    }
    /**
     * Create a new auto-incrementing small integer column on the table (2-byte, 0 to 65,535).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function small_increments($column)
    {
        return $this->unsigned_small_integer($column, true);
    }
    /**
     * Create a new auto-incrementing medium integer column on the table (3-byte, 0 to 16,777,215).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function medium_increments($column)
    {
        return $this->unsigned_medium_integer($column, true);
    }
    /**
     * Create a new auto-incrementing big integer column on the table (8-byte, 0 to 18,446,744,073,709,551,615).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function big_increments($column)
    {
        return $this->unsigned_big_integer($column, true);
    }
    /**
     * Create a new char column on the table.
     *
     * @param  string  $column
     * @param  int|null  $length
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function char($column, $length = null)
    {
        $length = !is_null($length) ? $length : Builder::$default_string_length;
        return $this->add_column('char', $column, compact('length'));
    }
    /**
     * Create a new string column on the table.
     *
     * @param  string  $column
     * @param  int|null  $length
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function string($column, $length = null)
    {
        $length = $length ?: Builder::$default_string_length;
        return $this->add_column('string', $column, compact('length'));
    }
    /**
     * Create a new tiny text column on the table (up to 255 characters).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function tiny_text($column)
    {
        return $this->add_column('tinyText', $column);
    }
    /**
     * Create a new text column on the table (up to 65,535 characters / ~64 KB).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function text($column)
    {
        return $this->add_column('text', $column);
    }
    /**
     * Create a new medium text column on the table (up to 16,777,215 characters / ~16 MB).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function medium_text($column)
    {
        return $this->add_column('mediumText', $column);
    }
    /**
     * Create a new long text column on the table (up to 4,294,967,295 characters / ~4 GB).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function long_text($column)
    {
        return $this->add_column('longText', $column);
    }
    /**
     * Create a new integer (4-byte) column on the table.
     * Range: -2,147,483,648 to 2,147,483,647 (signed) or 0 to 4,294,967,295 (unsigned).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function integer($column, $auto_increment = false, $unsigned = false)
    {
        return $this->add_column('integer', $column, compact('autoIncrement', 'unsigned'));
    }
    /**
     * Create a new tiny integer (1-byte) column on the table.
     * Range: -128 to 127 (signed) or 0 to 255 (unsigned).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function tiny_integer($column, $auto_increment = false, $unsigned = false)
    {
        return $this->add_column('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }
    /**
     * Create a new small integer (2-byte) column on the table.
     * Range: -32,768 to 32,767 (signed) or 0 to 65,535 (unsigned).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function small_integer($column, $auto_increment = false, $unsigned = false)
    {
        return $this->add_column('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }
    /**
     * Create a new medium integer (3-byte) column on the table.
     * Range: -8,388,608 to 8,388,607 (signed) or 0 to 16,777,215 (unsigned).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function medium_integer($column, $auto_increment = false, $unsigned = false)
    {
        return $this->add_column('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }
    /**
     * Create a new big integer (8-byte) column on the table.
     * Range: -9,223,372,036,854,775,808 to 9,223,372,036,854,775,807 (signed) or 0 to 18,446,744,073,709,551,615 (unsigned).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function big_integer($column, $auto_increment = false, $unsigned = false)
    {
        return $this->add_column('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }
    /**
     * Create a new unsigned integer column on the table (4-byte, 0 to 4,294,967,295).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function unsigned_integer($column, $auto_increment = false)
    {
        return $this->integer($column, $auto_increment, true);
    }
    /**
     * Create a new unsigned tiny integer column on the table (1-byte, 0 to 255).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function unsigned_tiny_integer($column, $auto_increment = false)
    {
        return $this->tiny_integer($column, $auto_increment, true);
    }
    /**
     * Create a new unsigned small integer column on the table (2-byte, 0 to 65,535).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function unsigned_small_integer($column, $auto_increment = false)
    {
        return $this->small_integer($column, $auto_increment, true);
    }
    /**
     * Create a new unsigned medium integer column on the table (3-byte, 0 to 16,777,215).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function unsigned_medium_integer($column, $auto_increment = false)
    {
        return $this->medium_integer($column, $auto_increment, true);
    }
    /**
     * Create a new unsigned big integer column on the table (8-byte, 0 to 18,446,744,073,709,551,615).
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function unsigned_big_integer($column, $auto_increment = false)
    {
        return $this->big_integer($column, $auto_increment, true);
    }
    /**
     * Create a new unsigned big integer column on the table (8-byte, 0 to 18,446,744,073,709,551,615).
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ForeignIdColumnDefinition
     */
    public function foreign_id($column)
    {
        return $this->add_column_definition(new Foreign_Id_Column_Definition($this, ['type' => 'bigInteger', 'name' => $column, 'autoIncrement' => false, 'unsigned' => true]));
    }
    /**
     * Create a foreign ID column for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string|null  $column
     * @return \Illuminate\Database\Schema\ForeignIdColumnDefinition
     */
    public function foreign_id_for($model, $column = null)
    {
        if (is_string($model)) {
            $model = new $model();
        }
        $column = $column ?: $model->get_foreign_key();
        if ($model->get_key_type() === 'int') {
            return $this->foreign_id($column)->table($model->get_table())->references_model_column($model->get_key_name());
        }
        $model_traits = class_uses_recursive($model);
        if (in_array(Has_Ulids::class, $model_traits, true)) {
            return $this->foreign_ulid($column, 26)->table($model->get_table())->references_model_column($model->get_key_name());
        }
        return $this->foreign_uuid($column)->table($model->get_table())->references_model_column($model->get_key_name());
    }
    /**
     * Create a new float column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function float($column, $precision = 53)
    {
        return $this->add_column('float', $column, compact('precision'));
    }
    /**
     * Create a new double column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function double($column)
    {
        return $this->add_column('double', $column);
    }
    /**
     * Create a new decimal column on the table.
     *
     * @param  string  $column
     * @param  int  $total
     * @param  int  $places
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function decimal($column, $total = 8, $places = 2)
    {
        return $this->add_column('decimal', $column, compact('total', 'places'));
    }
    /**
     * Create a new boolean column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function boolean($column)
    {
        return $this->add_column('boolean', $column);
    }
    /**
     * Create a new enum column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function enum($column, array $allowed)
    {
        $allowed = array_map(fn($value) => enum_value($value), $allowed);
        return $this->add_column('enum', $column, compact('allowed'));
    }
    /**
     * Create a new set column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function set($column, array $allowed)
    {
        return $this->add_column('set', $column, compact('allowed'));
    }
    /**
     * Create a new json column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function json($column)
    {
        return $this->add_column('json', $column);
    }
    /**
     * Create a new jsonb column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function jsonb($column)
    {
        return $this->add_column('jsonb', $column);
    }
    /**
     * Create a new date column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function date($column)
    {
        return $this->add_column('date', $column);
    }
    /**
     * Create a new date-time column on the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function date_time($column, $precision = null)
    {
        $precision ??= $this->default_time_precision();
        return $this->add_column('dateTime', $column, compact('precision'));
    }
    /**
     * Create a new date-time column (with time zone) on the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function date_time_tz($column, $precision = null)
    {
        $precision ??= $this->default_time_precision();
        return $this->add_column('dateTimeTz', $column, compact('precision'));
    }
    /**
     * Create a new time column on the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function time($column, $precision = null)
    {
        $precision ??= $this->default_time_precision();
        return $this->add_column('time', $column, compact('precision'));
    }
    /**
     * Create a new time column (with time zone) on the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function time_tz($column, $precision = null)
    {
        $precision ??= $this->default_time_precision();
        return $this->add_column('timeTz', $column, compact('precision'));
    }
    /**
     * Create a new timestamp column on the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function timestamp($column, $precision = null)
    {
        $precision ??= $this->default_time_precision();
        return $this->add_column('timestamp', $column, compact('precision'));
    }
    /**
     * Create a new timestamp (with time zone) column on the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function timestamp_tz($column, $precision = null)
    {
        $precision ??= $this->default_time_precision();
        return $this->add_column('timestampTz', $column, compact('precision'));
    }
    /**
     * Add nullable creation and update timestamps to the table.
     *
     * @param  int|null  $precision
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Schema\ColumnDefinition>
     */
    public function timestamps($precision = null): \Illuminate\Support\Collection
    {
        return new Collection([$this->timestamp('created_at', $precision)->nullable(), $this->timestamp('updated_at', $precision)->nullable()]);
    }
    /**
     * Add nullable creation and update timestamps to the table.
     *
     * Alias for self::timestamps().
     *
     * @param  int|null  $precision
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Schema\ColumnDefinition>
     */
    public function nullable_timestamps($precision = null): \Illuminate\Support\Collection
    {
        return $this->timestamps($precision);
    }
    /**
     * Add nullable creation and update timestampTz columns to the table.
     *
     * @param  int|null  $precision
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Schema\ColumnDefinition>
     */
    public function timestamps_tz($precision = null): \Illuminate\Support\Collection
    {
        return new Collection([$this->timestamp_tz('created_at', $precision)->nullable(), $this->timestamp_tz('updated_at', $precision)->nullable()]);
    }
    /**
     * Add nullable creation and update timestampTz columns to the table.
     *
     * Alias for self::timestampsTz().
     *
     * @param  int|null  $precision
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Schema\ColumnDefinition>
     */
    public function nullable_timestamps_tz($precision = null): \Illuminate\Support\Collection
    {
        return $this->timestamps_tz($precision);
    }
    /**
     * Add creation and update datetime columns to the table.
     *
     * @param  int|null  $precision
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Schema\ColumnDefinition>
     */
    public function datetimes($precision = null): \Illuminate\Support\Collection
    {
        return new Collection([$this->datetime('created_at', $precision)->nullable(), $this->datetime('updated_at', $precision)->nullable()]);
    }
    /**
     * Add a "deleted at" timestamp for the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function soft_deletes($column = 'deleted_at', $precision = null)
    {
        return $this->timestamp($column, $precision)->nullable();
    }
    /**
     * Add a "deleted at" timestampTz for the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function soft_deletes_tz($column = 'deleted_at', $precision = null)
    {
        return $this->timestamp_tz($column, $precision)->nullable();
    }
    /**
     * Add a "deleted at" datetime column to the table.
     *
     * @param  string  $column
     * @param  int|null  $precision
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function soft_deletes_datetime($column = 'deleted_at', $precision = null)
    {
        return $this->datetime($column, $precision)->nullable();
    }
    /**
     * Create a new year column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function year($column)
    {
        return $this->add_column('year', $column);
    }
    /**
     * Create a new binary column on the table.
     *
     * @param  string  $column
     * @param  int|null  $length
     * @param  bool  $fixed
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function binary($column, $length = null, $fixed = false)
    {
        return $this->add_column('binary', $column, compact('length', 'fixed'));
    }
    /**
     * Create a new UUID column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function uuid($column = 'uuid')
    {
        return $this->add_column('uuid', $column);
    }
    /**
     * Create a new UUID column on the table with a foreign key constraint.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ForeignIdColumnDefinition
     */
    public function foreign_uuid($column)
    {
        return $this->add_column_definition(new Foreign_Id_Column_Definition($this, ['type' => 'uuid', 'name' => $column]));
    }
    /**
     * Create a new ULID column on the table.
     *
     * @param  string  $column
     * @param  int|null  $length
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function ulid($column = 'ulid', $length = 26)
    {
        return $this->char($column, $length);
    }
    /**
     * Create a new ULID column on the table with a foreign key constraint.
     *
     * @param  string  $column
     * @param  int|null  $length
     * @return \Illuminate\Database\Schema\ForeignIdColumnDefinition
     */
    public function foreign_ulid($column, $length = 26)
    {
        return $this->add_column_definition(new Foreign_Id_Column_Definition($this, ['type' => 'char', 'name' => $column, 'length' => $length]));
    }
    /**
     * Create a new IP address column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function ip_address($column = 'ip_address')
    {
        return $this->add_column('ipAddress', $column);
    }
    /**
     * Create a new MAC address column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function mac_address($column = 'mac_address')
    {
        return $this->add_column('macAddress', $column);
    }
    /**
     * Create a new geometry column on the table.
     *
     * @param  string  $column
     * @param  string|null  $subtype
     * @param  int  $srid
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function geometry($column, $subtype = null, $srid = 0)
    {
        return $this->add_column('geometry', $column, compact('subtype', 'srid'));
    }
    /**
     * Create a new geography column on the table.
     *
     * @param  string  $column
     * @param  string|null  $subtype
     * @param  int  $srid
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function geography($column, $subtype = null, $srid = 4326)
    {
        return $this->add_column('geography', $column, compact('subtype', 'srid'));
    }
    /**
     * Create a new generated, computed column on the table.
     *
     * @param  string  $column
     * @param  string  $expression
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function computed($column, $expression)
    {
        return $this->add_column('computed', $column, compact('expression'));
    }
    /**
     * Create a new vector column on the table.
     *
     * @param  string  $column
     * @param  int|null  $dimensions
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function vector($column, $dimensions = null)
    {
        $options = $dimensions ? compact('dimensions') : [];
        return $this->add_column('vector', $column, $options);
    }
    /**
     * Create a new tsvector column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function tsvector($column)
    {
        return $this->add_column('tsvector', $column);
    }
    /**
     * Add the proper columns for a polymorphic table.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @param  string|null  $after
     */
    public function morphs($name, $index_name = null, $after = null): void
    {
        if (Builder::$default_morph_key_type === 'uuid') {
            $this->uuid_morphs($name, $index_name, $after);
        } elseif (Builder::$default_morph_key_type === 'ulid') {
            $this->ulid_morphs($name, $index_name, $after);
        } else {
            $this->numeric_morphs($name, $index_name, $after);
        }
    }
    /**
     * Add nullable columns for a polymorphic table.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @param  string|null  $after
     */
    public function nullable_morphs($name, $index_name = null, $after = null): void
    {
        if (Builder::$default_morph_key_type === 'uuid') {
            $this->nullable_uuid_morphs($name, $index_name, $after);
        } elseif (Builder::$default_morph_key_type === 'ulid') {
            $this->nullable_ulid_morphs($name, $index_name, $after);
        } else {
            $this->nullable_numeric_morphs($name, $index_name, $after);
        }
    }
    /**
     * Add the proper columns for a polymorphic table using numeric IDs (incremental).
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @param  string|null  $after
     */
    public function numeric_morphs($name, $index_name = null, $after = null): void
    {
        $this->string("{$name}_type")->after($after);
        $this->unsigned_big_integer("{$name}_id")->after(!is_null($after) ? "{$name}_type" : null);
        $this->index(["{$name}_type", "{$name}_id"], $index_name);
    }
    /**
     * Add nullable columns for a polymorphic table using numeric IDs (incremental).
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @param  string|null  $after
     */
    public function nullable_numeric_morphs($name, $index_name = null, $after = null): void
    {
        $this->string("{$name}_type")->nullable()->after($after);
        $this->unsigned_big_integer("{$name}_id")->nullable()->after(!is_null($after) ? "{$name}_type" : null);
        $this->index(["{$name}_type", "{$name}_id"], $index_name);
    }
    /**
     * Add the proper columns for a polymorphic table using UUIDs.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @param  string|null  $after
     */
    public function uuid_morphs($name, $index_name = null, $after = null): void
    {
        $this->string("{$name}_type")->after($after);
        $this->uuid("{$name}_id")->after(!is_null($after) ? "{$name}_type" : null);
        $this->index(["{$name}_type", "{$name}_id"], $index_name);
    }
    /**
     * Add nullable columns for a polymorphic table using UUIDs.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @param  string|null  $after
     */
    public function nullable_uuid_morphs($name, $index_name = null, $after = null): void
    {
        $this->string("{$name}_type")->nullable()->after($after);
        $this->uuid("{$name}_id")->nullable()->after(!is_null($after) ? "{$name}_type" : null);
        $this->index(["{$name}_type", "{$name}_id"], $index_name);
    }
    /**
     * Add the proper columns for a polymorphic table using ULIDs.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @param  string|null  $after
     */
    public function ulid_morphs($name, $index_name = null, $after = null): void
    {
        $this->string("{$name}_type")->after($after);
        $this->ulid("{$name}_id")->after(!is_null($after) ? "{$name}_type" : null);
        $this->index(["{$name}_type", "{$name}_id"], $index_name);
    }
    /**
     * Add nullable columns for a polymorphic table using ULIDs.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @param  string|null  $after
     */
    public function nullable_ulid_morphs($name, $index_name = null, $after = null): void
    {
        $this->string("{$name}_type")->nullable()->after($after);
        $this->ulid("{$name}_id")->nullable()->after(!is_null($after) ? "{$name}_type" : null);
        $this->index(["{$name}_type", "{$name}_id"], $index_name);
    }
    /**
     * Add the `remember_token` column to the table.
     *
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function remember_token()
    {
        return $this->string('remember_token', 100)->nullable();
    }
    /**
     * Create a new custom column on the table.
     *
     * @param  string  $column
     * @param  string  $definition
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function raw_column($column, $definition)
    {
        return $this->add_column('raw', $column, compact('definition'));
    }
    /**
     * Add a comment to the table.
     *
     * @param  string  $comment
     * @return \Illuminate\Support\Fluent
     */
    public function comment($comment)
    {
        return $this->add_command('tableComment', compact('comment'));
    }
    /**
     * Create a new index command on the blueprint.
     *
     * @param  string  $type
     * @param  string|array  $columns
     * @param  string  $index
     * @param  string|null  $algorithm
     * @param  string|null  $operatorClass
     * @return \Illuminate\Support\Fluent
     */
    protected function index_command($type, $columns, $index, $algorithm = null, $operator_class = null)
    {
        $columns = (array) $columns;
        // If no name was specified for this index, we will create one using a basic
        // convention of the table name, followed by the columns, followed by an
        // index type, such as primary or index, which makes the index unique.
        $index = $index ?: $this->create_index_name($type, $columns);
        return $this->add_command($type, compact('index', 'columns', 'algorithm', 'operatorClass'));
    }
    /**
     * Create a new drop index command on the blueprint.
     *
     * @param  string  $command
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    protected function drop_index_command($command, string $type, $index)
    {
        $columns = [];
        // If the given "index" is actually an array of columns, the developer means
        // to drop an index merely by specifying the columns involved without the
        // conventional name, so we will build the index name from the columns.
        if (is_array($index)) {
            $index = $this->create_index_name($type, $columns = $index);
        }
        return $this->index_command($command, $columns, $index);
    }
    /**
     * Create a default index name for the table.
     */
    protected function create_index_name(string $type, array $columns): string
    {
        $table = $this->table;
        if ($this->connection->get_config('prefix_indexes')) {
            $table = str_contains($this->table, '.') ? substr_replace($this->table, '.' . $this->connection->get_table_prefix(), strrpos($this->table, '.'), 1) : $this->connection->get_table_prefix() . $this->table;
        }
        $index = strtolower($table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $index);
    }
    /**
     * Add a new column to the blueprint.
     *
     * @param  string  $type
     * @param  string  $name
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function add_column($type, $name, array $parameters = [])
    {
        return $this->add_column_definition(new Column_Definition(array_merge(compact('type', 'name'), $parameters)));
    }
    /**
     * Add a new column definition to the blueprint.
     *
     * @param  \Illuminate\Database\Schema\ColumnDefinition  $definition
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    protected function add_column_definition($definition)
    {
        $this->columns[] = $definition;
        if (!$this->creating()) {
            $this->commands[] = $definition;
        }
        if ($this->after) {
            $definition->after($this->after);
            $this->after = $definition->name;
        }
        return $definition;
    }
    /**
     * Add the columns from the callback after the given column.
     *
     * @param  string  $column
     * @param  (\Closure(self): void)  $callback
     */
    public function after($column, Closure $callback): void
    {
        $this->after = $column;
        $callback($this);
        $this->after = null;
    }
    /**
     * Remove a column from the schema blueprint.
     *
     * @param  string  $name
     * @return $this
     */
    public function remove_column($name): static
    {
        $this->columns = array_values(array_filter($this->columns, fn(\Illuminate\Database\Schema\Column_Definition $c): bool => $c['name'] != $name));
        $this->commands = array_values(array_filter($this->commands, fn(\Illuminate\Support\Fluent $c): bool => !$c instanceof Column_Definition || $c['name'] != $name));
        return $this;
    }
    /**
     * Add a new command to the blueprint.
     *
     * @param  string  $name
     * @return \Illuminate\Support\Fluent
     */
    protected function add_command($name, array $parameters = [])
    {
        $this->commands[] = $command = $this->create_command($name, $parameters);
        return $command;
    }
    /**
     * Create a new Fluent command.
     *
     * @param  string  $name
     */
    protected function create_command($name, array $parameters = []): \Illuminate\Support\Fluent
    {
        return new Fluent(array_merge(compact('name'), $parameters));
    }
    /**
     * Get the table the blueprint describes.
     *
     * @return string
     */
    public function get_table()
    {
        return $this->table;
    }
    /**
     * Get the table prefix.
     *
     * @deprecated Use DB::getTablePrefix()
     *
     * @return string
     */
    public function get_prefix()
    {
        return $this->connection->get_table_prefix();
    }
    /**
     * Get the columns on the blueprint.
     *
     * @return \Illuminate\Database\Schema\ColumnDefinition[]
     */
    public function get_columns()
    {
        return $this->columns;
    }
    /**
     * Get the commands on the blueprint.
     *
     * @return \Illuminate\Support\Fluent[]
     */
    public function get_commands()
    {
        return $this->commands;
    }
    /**
     * Determine if the blueprint has state.
     */
    private function has_state(): bool
    {
        return !is_null($this->state);
    }
    /**
     * Get the state of the blueprint.
     *
     * @return \Illuminate\Database\Schema\BlueprintState
     */
    public function get_state()
    {
        return $this->state;
    }
    /**
     * Get the columns on the blueprint that should be added.
     *
     * @return \Illuminate\Database\Schema\ColumnDefinition[]
     */
    public function get_added_columns(): array
    {
        return array_filter($this->columns, fn(\Illuminate\Database\Schema\Column_Definition $column): bool => !$column->change);
    }
    /**
     * Get the columns on the blueprint that should be changed.
     *
     * @deprecated Will be removed in a future Laravel version.
     *
     * @return \Illuminate\Database\Schema\ColumnDefinition[]
     */
    public function get_changed_columns(): array
    {
        return array_filter($this->columns, fn(\Illuminate\Database\Schema\Column_Definition $column): bool => (bool) $column->change);
    }
    /**
     * Get the default time precision.
     */
    protected function default_time_precision(): ?int
    {
        return $this->connection->get_schema_builder()::$default_time_precision;
    }
}