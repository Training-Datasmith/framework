<?php

declare(strict_types=1);

namespace Illuminate\Database\Console;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

use function Laravel\Prompts\search;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'db:table')]
class TableCommand extends DatabaseInspectionCommand
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
    public function handle(ConnectionResolverInterface $connections): int
    {
        $connection = $connections->connection($this->input->getOption('database'));
        $tables = (new Collection($connection->getSchemaBuilder()->getTables()))
            ->keyBy('schema_qualified_name')->all();

        $tableNames = (new Collection($tables))->keys();

        $tableName = $this->argument('table') ?: search(
            'Which table would you like to inspect?',
            fn (string $query) => $tableNames
                ->filter(fn ($table): bool => str_contains(strtolower((string) $table), strtolower($query)))
                ->values()
                ->all()
        );

        $table = $tables[$tableName] ?? (new Collection($tables))->when(
            Arr::wrap($connection->getSchemaBuilder()->getCurrentSchemaListing()
                ?? $connection->getSchemaBuilder()->getCurrentSchemaName()),
            fn (Collection $collection, array $currentSchemas): \Illuminate\Support\Collection => $collection->sortBy(
                function (array $table) use ($currentSchemas): int|string {
                    $index = array_search($table['schema'], $currentSchemas);

                    return $index === false ? PHP_INT_MAX : $index;
                }
            )
        )->firstWhere('name', $tableName);

        if (! $table) {
            $this->components->warn("Table [{$tableName}] doesn't exist.");

            return 1;
        }

        [$columns, $indexes, $foreignKeys] = $connection->withoutTablePrefix(function ($connection) use ($table): array {
            $schema = $connection->getSchemaBuilder();
            $tableName = $table['schema_qualified_name'];

            return [
                $this->columns($schema, $tableName),
                $this->indexes($schema, $tableName),
                $this->foreignKeys($schema, $tableName),
            ];
        });

        $data = [
            'table' => [
                'schema' => $table['schema'],
                'name' => $table['name'],
                'schema_qualified_name' => $table['schema_qualified_name'],
                'columns' => count($columns),
                'size' => $table['size'],
                'comment' => $table['comment'],
                'collation' => $table['collation'],
                'engine' => $table['engine'],
            ],
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ];

        $this->display($data);

        return 0;
    }

    /**
     * Get the information regarding the table's columns.
     */
    protected function columns(Builder $schema, string $table): \Illuminate\Support\Collection
    {
        return (new Collection($schema->getColumns($table)))->map(fn (array $column): array => [
            'column' => $column['name'],
            'attributes' => $this->getAttributesForColumn($column),
            'default' => $column['default'],
            'type' => $column['type'],
        ]);
    }

    /**
     * Get the attributes for a table column.
     */
    protected function getAttributesForColumn(array $column): \Illuminate\Support\Collection
    {
        return (new Collection([
            $column['type_name'],
            $column['generation'] ? $column['generation']['type'] : null,
            $column['auto_increment'] ? 'autoincrement' : null,
            $column['nullable'] ? 'nullable' : null,
            $column['collation'],
        ]))->filter();
    }

    /**
     * Get the information regarding the table's indexes.
     */
    protected function indexes(Builder $schema, string $table): \Illuminate\Support\Collection
    {
        return (new Collection($schema->getIndexes($table)))->map(fn (array $index): array => [
            'name' => $index['name'],
            'columns' => new Collection($index['columns']),
            'attributes' => $this->getAttributesForIndex($index),
        ]);
    }

    /**
     * Get the attributes for a table index.
     */
    protected function getAttributesForIndex(array $index): \Illuminate\Support\Collection
    {
        return (new Collection([
            $index['type'],
            count($index['columns']) > 1 ? 'compound' : null,
            $index['unique'] && ! $index['primary'] ? 'unique' : null,
            $index['primary'] ? 'primary' : null,
        ]))->filter();
    }

    /**
     * Get the information regarding the table's foreign keys.
     */
    protected function foreignKeys(Builder $schema, string $table): \Illuminate\Support\Collection
    {
        return (new Collection($schema->getForeignKeys($table)))->map(fn ($foreignKey): array => [
            'name' => $foreignKey['name'],
            'columns' => new Collection($foreignKey['columns']),
            'foreign_schema' => $foreignKey['foreign_schema'],
            'foreign_table' => $foreignKey['foreign_table'],
            'foreign_columns' => new Collection($foreignKey['foreign_columns']),
            'on_update' => $foreignKey['on_update'],
            'on_delete' => $foreignKey['on_delete'],
        ]);
    }

    /**
     * Render the table information.
     *
     * @return void
     */
    protected function display(array $data)
    {
        $this->option('json') ? $this->displayJson($data) : $this->displayForCli($data);
    }

    /**
     * Render the table information as JSON.
     *
     * @return void
     */
    protected function displayJson(array $data)
    {
        $this->output->writeln(json_encode($data));
    }

    /**
     * Render the table information formatted for the CLI.
     *
     * @return void
     */
    protected function displayForCli(array $data)
    {
        [$table, $columns, $indexes, $foreignKeys] = [
            $data['table'], $data['columns'], $data['indexes'], $data['foreign_keys'],
        ];

        $this->newLine();

        $this->components->twoColumnDetail('<fg=green;options=bold>'.$table['schema_qualified_name'].'</>', $table['comment'] ? '<fg=gray>'.$table['comment'].'</>' : null);
        $this->components->twoColumnDetail('Columns', $table['columns']);

        if (! is_null($table['size'])) {
            $this->components->twoColumnDetail('Size', Number::fileSize($table['size'], 2));
        }

        if ($table['engine']) {
            $this->components->twoColumnDetail('Engine', $table['engine']);
        }

        if ($table['collation']) {
            $this->components->twoColumnDetail('Collation', $table['collation']);
        }

        $this->newLine();

        if ($columns->isNotEmpty()) {
            $this->components->twoColumnDetail('<fg=green;options=bold>Column</>', 'Type');

            $columns->each(function (array $column): void {
                $this->components->twoColumnDetail(
                    $column['column'].' <fg=gray>'.$column['attributes']->implode(', ').'</>',
                    (! is_null($column['default']) ? '<fg=gray>'.$column['default'].'</> ' : '').$column['type']
                );
            });

            $this->newLine();
        }

        if ($indexes->isNotEmpty()) {
            $this->components->twoColumnDetail('<fg=green;options=bold>Index</>');

            $indexes->each(function (array $index): void {
                $this->components->twoColumnDetail(
                    $index['name'].' <fg=gray>'.$index['columns']->implode(', ').'</>',
                    $index['attributes']->implode(', ')
                );
            });

            $this->newLine();
        }

        if ($foreignKeys->isNotEmpty()) {
            $this->components->twoColumnDetail('<fg=green;options=bold>Foreign Key</>', 'On Update / On Delete');

            $foreignKeys->each(function (array $foreignKey): void {
                $this->components->twoColumnDetail(
                    $foreignKey['name'].' <fg=gray;options=bold>'.$foreignKey['columns']->implode(', ').' references '.$foreignKey['foreign_columns']->implode(', ').' on '.$foreignKey['foreign_table'].'</>',
                    $foreignKey['on_update'].' / '.$foreignKey['on_delete'],
                );
            });

            $this->newLine();
        }
    }
}
