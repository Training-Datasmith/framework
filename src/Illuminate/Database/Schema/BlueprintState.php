<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
class Blueprint_State
{
    /**
     * The columns.
     *
     * @var \Illuminate\Database\Schema\ColumnDefinition[]
     */
    private $columns;
    /**
     * The primary key.
     *
     * @var \Illuminate\Database\Schema\IndexDefinition|null
     */
    private $primary_key;
    /**
     * The indexes.
     *
     * @var \Illuminate\Database\Schema\IndexDefinition[]
     */
    private $indexes;
    /**
     * The foreign keys.
     *
     * @var \Illuminate\Database\Schema\ForeignKeyDefinition[]
     */
    private $foreign_keys;
    /**
     * Create a new blueprint state instance.
     */
    public function __construct(
        /**
         * The blueprint instance.
         */
        protected \Illuminate\Database\Schema\Blueprint $blueprint,
        /**
         * The connection instance.
         */
        protected \Illuminate\Database\Connection $connection
    )
    {
        $schema = $this->connection->get_schema_builder();
        $table = $this->blueprint->get_table();
        $this->columns = (new Collection($schema->get_columns($table)))->map(fn($column): \Illuminate\Database\Schema\Column_Definition => new Column_Definition(['name' => $column['name'], 'type' => $column['type_name'], 'full_type_definition' => $column['type'], 'nullable' => $column['nullable'], 'default' => is_null($column['default']) ? null : new Expression(Str::wrap($column['default'], '(', ')')), 'autoIncrement' => $column['auto_increment'], 'collation' => $column['collation'], 'comment' => $column['comment'], 'virtualAs' => !is_null($column['generation']) && $column['generation']['type'] === 'virtual' ? $column['generation']['expression'] : null, 'storedAs' => !is_null($column['generation']) && $column['generation']['type'] === 'stored' ? $column['generation']['expression'] : null]))->all();
        [$primary, $indexes] = (new Collection($schema->get_indexes($table)))->map(fn($index): \Illuminate\Database\Schema\Index_Definition => new Index_Definition(['name' => match (true) {
            $index['primary'] => 'primary',
            $index['unique'] => 'unique',
            default => 'index',
        }, 'index' => $index['name'], 'columns' => $index['columns']]))->partition(fn($index): bool => $index->name === 'primary');
        $this->indexes = $indexes->all();
        $this->primary_key = $primary->first();
        $this->foreign_keys = (new Collection($schema->get_foreign_keys($table)))->map(fn($foreign_key): \Illuminate\Database\Schema\Foreign_Key_Definition => new Foreign_Key_Definition(['columns' => $foreign_key['columns'], 'on' => new Expression($foreign_key['foreign_table']), 'references' => $foreign_key['foreign_columns'], 'onUpdate' => $foreign_key['on_update'], 'onDelete' => $foreign_key['on_delete']]))->all();
    }
    /**
     * Get the primary key.
     *
     * @return \Illuminate\Database\Schema\IndexDefinition|null
     */
    public function get_primary_key()
    {
        return $this->primary_key;
    }
    /**
     * Get the columns.
     *
     * @return \Illuminate\Database\Schema\ColumnDefinition[]
     */
    public function get_columns()
    {
        return $this->columns;
    }
    /**
     * Get the indexes.
     *
     * @return \Illuminate\Database\Schema\IndexDefinition[]
     */
    public function get_indexes()
    {
        return $this->indexes;
    }
    /**
     * Get the foreign keys.
     *
     * @return \Illuminate\Database\Schema\ForeignKeyDefinition[]
     */
    public function get_foreign_keys()
    {
        return $this->foreign_keys;
    }
    /*
     * Update the blueprint's state.
     *
     * @param  \Illuminate\Support\Fluent  $command
     * @return void
     */
    public function update(Fluent $command): void
    {
        switch ($command->name) {
            case 'alter':
                // Already handled...
                break;
            case 'add':
                $this->columns[] = $command->column;
                break;
            case 'change':
                foreach ($this->columns as &$column) {
                    if ($column->name === $command->column->name) {
                        $column = $command->column;
                        break;
                    }
                }
                break;
            case 'renameColumn':
                foreach ($this->columns as $column) {
                    if ($column->name === $command->from) {
                        $column->name = $command->to;
                        break;
                    }
                }
                if ($this->primary_key) {
                    $this->primary_key->columns = str_replace($command->from, $command->to, $this->primary_key->columns);
                }
                foreach ($this->indexes as $index) {
                    $index->columns = str_replace($command->from, $command->to, $index->columns);
                }
                foreach ($this->foreign_keys as $foreign_key) {
                    $foreign_key->columns = str_replace($command->from, $command->to, $foreign_key->columns);
                }
                break;
            case 'dropColumn':
                $this->columns = array_values(array_filter($this->columns, fn(\Illuminate\Database\Schema\Column_Definition $column): bool => !in_array($column->name, $command->columns)));
                break;
            case 'primary':
                $this->primary_key = $command;
                break;
            case 'unique':
            case 'index':
                $this->indexes[] = $command;
                break;
            case 'renameIndex':
                foreach ($this->indexes as $index) {
                    if ($index->index === $command->from) {
                        $index->index = $command->to;
                        break;
                    }
                }
                break;
            case 'foreign':
                $this->foreign_keys[] = $command;
                break;
            case 'dropPrimary':
                $this->primary_key = null;
                break;
            case 'dropIndex':
            case 'dropUnique':
                $this->indexes = array_values(array_filter($this->indexes, fn(\Illuminate\Database\Schema\Index_Definition $index): bool => $index->index !== $command->index));
                break;
            case 'dropForeign':
                $this->foreign_keys = array_values(array_filter($this->foreign_keys, fn(\Illuminate\Database\Schema\Foreign_Key_Definition $fk): bool => $fk->columns !== $command->columns));
                break;
        }
    }
}