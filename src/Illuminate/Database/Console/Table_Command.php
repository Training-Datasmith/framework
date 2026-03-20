<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Database\Connection_Resolver_Interface;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use function Laravel\Prompts\search;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'db:table')]
class Table_Command extends Database_Inspection_Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:table
                            {table? : The name of the table}
                            {--database= : The database connection}
                            {--json : Output the table information as JSON}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display information about the given database table';
    /**
     * Execute the console command.
     */
    public function handle(Connection_Resolver_Interface $connections): int
    {
        $connection = $connections->connection($this->input->get_option('database'));
        $tables = (new Collection($connection->get_schema_builder()->get_tables()))->key_by('schema_qualified_name')->all();
        $table_names = (new Collection($tables))->keys();
        $table_name = $this->argument('table') ?: search('Which table would you like to inspect?', fn(string $query) => $table_names->filter(fn($table): bool => str_contains(strtolower((string) $table), strtolower($query)))->values()->all());
        $table = $tables[$table_name] ?? (new Collection($tables))->when(Arr::wrap($connection->get_schema_builder()->get_current_schema_listing() ?? $connection->get_schema_builder()->get_current_schema_name()), fn(Collection $collection, array $current_schemas): \Illuminate\Support\Collection => $collection->sort_by(function (array $table) use ($current_schemas): int|string {
            $index = array_search($table['schema'], $current_schemas);
            return $index === false ? PHP_INT_MAX : $index;
        }))->first_where('name', $table_name);
        if (!$table) {
            $this->components->warn("Table [{$table_name}] doesn't exist.");
            return 1;
        }
        [$columns, $indexes, $foreign_keys] = $connection->without_table_prefix(function ($connection) use ($table): array {
            $schema = $connection->get_schema_builder();
            $table_name = $table['schema_qualified_name'];
            return [$this->columns($schema, $table_name), $this->indexes($schema, $table_name), $this->foreign_keys($schema, $table_name)];
        });
        $data = ['table' => ['schema' => $table['schema'], 'name' => $table['name'], 'schema_qualified_name' => $table['schema_qualified_name'], 'columns' => count($columns), 'size' => $table['size'], 'comment' => $table['comment'], 'collation' => $table['collation'], 'engine' => $table['engine']], 'columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreign_keys];
        $this->display($data);
        return 0;
    }
    /**
     * Get the information regarding the table's columns.
     */
    protected function columns(Builder $schema, string $table): \Illuminate\Support\Collection
    {
        return (new Collection($schema->get_columns($table)))->map(fn(array $column): array => ['column' => $column['name'], 'attributes' => $this->get_attributes_for_column($column), 'default' => $column['default'], 'type' => $column['type']]);
    }
    /**
     * Get the attributes for a table column.
     */
    protected function get_attributes_for_column(array $column): \Illuminate\Support\Collection
    {
        return (new Collection([$column['type_name'], $column['generation'] ? $column['generation']['type'] : null, $column['auto_increment'] ? 'autoincrement' : null, $column['nullable'] ? 'nullable' : null, $column['collation']]))->filter();
    }
    /**
     * Get the information regarding the table's indexes.
     */
    protected function indexes(Builder $schema, string $table): \Illuminate\Support\Collection
    {
        return (new Collection($schema->get_indexes($table)))->map(fn(array $index): array => ['name' => $index['name'], 'columns' => new Collection($index['columns']), 'attributes' => $this->get_attributes_for_index($index)]);
    }
    /**
     * Get the attributes for a table index.
     */
    protected function get_attributes_for_index(array $index): \Illuminate\Support\Collection
    {
        return (new Collection([$index['type'], count($index['columns']) > 1 ? 'compound' : null, $index['unique'] && !$index['primary'] ? 'unique' : null, $index['primary'] ? 'primary' : null]))->filter();
    }
    /**
     * Get the information regarding the table's foreign keys.
     */
    protected function foreign_keys(Builder $schema, string $table): \Illuminate\Support\Collection
    {
        return (new Collection($schema->get_foreign_keys($table)))->map(fn($foreign_key): array => ['name' => $foreign_key['name'], 'columns' => new Collection($foreign_key['columns']), 'foreign_schema' => $foreign_key['foreign_schema'], 'foreign_table' => $foreign_key['foreign_table'], 'foreign_columns' => new Collection($foreign_key['foreign_columns']), 'on_update' => $foreign_key['on_update'], 'on_delete' => $foreign_key['on_delete']]);
    }
    /**
     * Render the table information.
     *
     * @return void
     */
    protected function display(array $data)
    {
        $this->option('json') ? $this->display_json($data) : $this->display_for_cli($data);
    }
    /**
     * Render the table information as JSON.
     *
     * @return void
     */
    protected function display_json(array $data)
    {
        $this->output->writeln(json_encode($data));
    }
    /**
     * Render the table information formatted for the CLI.
     *
     * @return void
     */
    protected function display_for_cli(array $data)
    {
        [$table, $columns, $indexes, $foreign_keys] = [$data['table'], $data['columns'], $data['indexes'], $data['foreign_keys']];
        $this->new_line();
        $this->components->two_column_detail('<fg=green;options=bold>' . $table['schema_qualified_name'] . '</>', $table['comment'] ? '<fg=gray>' . $table['comment'] . '</>' : null);
        $this->components->two_column_detail('Columns', $table['columns']);
        if (!is_null($table['size'])) {
            $this->components->two_column_detail('Size', Number::file_size($table['size'], 2));
        }
        if ($table['engine']) {
            $this->components->two_column_detail('Engine', $table['engine']);
        }
        if ($table['collation']) {
            $this->components->two_column_detail('Collation', $table['collation']);
        }
        $this->new_line();
        if ($columns->is_not_empty()) {
            $this->components->two_column_detail('<fg=green;options=bold>Column</>', 'Type');
            $columns->each(function (array $column): void {
                $this->components->two_column_detail($column['column'] . ' <fg=gray>' . $column['attributes']->implode(', ') . '</>', (!is_null($column['default']) ? '<fg=gray>' . $column['default'] . '</> ' : '') . $column['type']);
            });
            $this->new_line();
        }
        if ($indexes->is_not_empty()) {
            $this->components->two_column_detail('<fg=green;options=bold>Index</>');
            $indexes->each(function (array $index): void {
                $this->components->two_column_detail($index['name'] . ' <fg=gray>' . $index['columns']->implode(', ') . '</>', $index['attributes']->implode(', '));
            });
            $this->new_line();
        }
        if ($foreign_keys->is_not_empty()) {
            $this->components->two_column_detail('<fg=green;options=bold>Foreign Key</>', 'On Update / On Delete');
            $foreign_keys->each(function (array $foreign_key): void {
                $this->components->two_column_detail($foreign_key['name'] . ' <fg=gray;options=bold>' . $foreign_key['columns']->implode(', ') . ' references ' . $foreign_key['foreign_columns']->implode(', ') . ' on ' . $foreign_key['foreign_table'] . '</>', $foreign_key['on_update'] . ' / ' . $foreign_key['on_delete']);
            });
            $this->new_line();
        }
    }
}