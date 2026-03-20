<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Database\Connection_Interface;
use Illuminate\Database\Connection_Resolver_Interface;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'db:show')]
class Show_Command extends Database_Inspection_Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:show {--database= : The database connection}
                {--json : Output the database information as JSON}
                {--counts : Show the table row count <bg=red;options=bold> Note: This can be slow on large databases </>}
                {--views : Show the database views <bg=red;options=bold> Note: This can be slow on large databases </>}
                {--types : Show the user defined types}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display information about the given database';
    /**
     * Execute the console command.
     */
    public function handle(Connection_Resolver_Interface $connections): int
    {
        $connection = $connections->connection($database = $this->input->get_option('database'));
        $schema = $connection->get_schema_builder();
        $data = ['platform' => ['config' => $this->get_config_from_database($database), 'name' => $connection->get_driver_title(), 'connection' => $connection->get_name(), 'version' => $connection->get_server_version(), 'open_connections' => $connection->thread_count()], 'tables' => $this->tables($connection, $schema)];
        if ($this->option('views')) {
            $data['views'] = $this->views($connection, $schema);
        }
        if ($this->option('types')) {
            $data['types'] = $this->types($connection, $schema);
        }
        $this->display($data);
        return 0;
    }
    /**
     * Get information regarding the tables within the database.
     */
    protected function tables(Connection_Interface $connection, Builder $schema): \Illuminate\Support\Collection
    {
        return (new Collection($schema->get_tables()))->map(fn($table): array => ['table' => $table['name'], 'schema' => $table['schema'], 'schema_qualified_name' => $table['schema_qualified_name'], 'size' => $table['size'], 'rows' => $this->option('counts') ? $connection->without_table_prefix(fn($connection) => $connection->table($table['schema_qualified_name'])->count()) : null, 'engine' => $table['engine'], 'collation' => $table['collation'], 'comment' => $table['comment']]);
    }
    /**
     * Get information regarding the views within the database.
     */
    protected function views(Connection_Interface $connection, Builder $schema): \Illuminate\Support\Collection
    {
        return (new Collection($schema->get_views()))->map(fn($view): array => ['view' => $view['name'], 'schema' => $view['schema'], 'rows' => $connection->without_table_prefix(fn($connection) => $connection->table($view['schema_qualified_name'])->count())]);
    }
    /**
     * Get information regarding the user-defined types within the database.
     */
    protected function types(Connection_Interface $connection, Builder $schema): \Illuminate\Support\Collection
    {
        return (new Collection($schema->get_types()))->map(fn($type): array => ['name' => $type['name'], 'schema' => $type['schema'], 'type' => $type['type'], 'category' => $type['category']]);
    }
    /**
     * Render the database information.
     *
     * @return void
     */
    protected function display(array $data)
    {
        $this->option('json') ? $this->display_json($data) : $this->display_for_cli($data);
    }
    /**
     * Render the database information as JSON.
     *
     * @return void
     */
    protected function display_json(array $data)
    {
        $this->output->writeln(json_encode($data));
    }
    /**
     * Render the database information formatted for the CLI.
     *
     * @return void
     */
    protected function display_for_cli(array $data)
    {
        $platform = $data['platform'];
        $tables = $data['tables'];
        $views = $data['views'] ?? null;
        $types = $data['types'] ?? null;
        $this->new_line();
        $this->components->two_column_detail('<fg=green;options=bold>' . $platform['name'] . '</>', $platform['version']);
        $this->components->two_column_detail('Connection', $platform['connection']);
        $this->components->two_column_detail('Database', Arr::get($platform['config'], 'database'));
        $this->components->two_column_detail('Host', Arr::get($platform['config'], 'host'));
        $this->components->two_column_detail('Port', Arr::get($platform['config'], 'port'));
        $this->components->two_column_detail('Username', Arr::get($platform['config'], 'username'));
        $this->components->two_column_detail('URL', Arr::get($platform['config'], 'url'));
        $this->components->two_column_detail('Open Connections', $platform['open_connections']);
        $this->components->two_column_detail('Tables', $tables->count());
        if ($table_size_sum = $tables->sum('size')) {
            $this->components->two_column_detail('Total Size', Number::file_size($table_size_sum, 2));
        }
        $this->new_line();
        if ($tables->is_not_empty()) {
            $has_schema = !is_null($tables->first()['schema']);
            $this->components->two_column_detail(($has_schema ? '<fg=green;options=bold>Schema</> <fg=gray;options=bold>/</> ' : '') . '<fg=green;options=bold>Table</>', 'Size' . ($this->option('counts') ? ' <fg=gray;options=bold>/</> <fg=yellow;options=bold>Rows</>' : ''));
            $tables->each(function (array $table): void {
                $table_size = is_null($table['size']) ? null : Number::file_size($table['size'], 2);
                $this->components->two_column_detail(($table['schema'] ? $table['schema'] . ' <fg=gray;options=bold>/</> ' : '') . $table['table'] . ($this->output->is_verbose() ? ' <fg=gray>' . $table['engine'] . '</>' : null), ($table_size ?? '—') . ($this->option('counts') ? ' <fg=gray;options=bold>/</> <fg=yellow;options=bold>' . Number::format($table['rows']) . '</>' : ''));
                if ($this->output->is_verbose()) {
                    if ($table['comment']) {
                        $this->components->bullet_list([$table['comment']]);
                    }
                }
            });
            $this->new_line();
        }
        if ($views && $views->is_not_empty()) {
            $has_schema = !is_null($views->first()['schema']);
            $this->components->two_column_detail(($has_schema ? '<fg=green;options=bold>Schema</> <fg=gray;options=bold>/</> ' : '') . '<fg=green;options=bold>View</>', '<fg=green;options=bold>Rows</>');
            $views->each(fn($view) => $this->components->two_column_detail(($view['schema'] ? $view['schema'] . ' <fg=gray;options=bold>/</> ' : '') . $view['view'], Number::format($view['rows'])));
            $this->new_line();
        }
        if ($types && $types->is_not_empty()) {
            $has_schema = !is_null($types->first()['schema']);
            $this->components->two_column_detail(($has_schema ? '<fg=green;options=bold>Schema</> <fg=gray;options=bold>/</> ' : '') . '<fg=green;options=bold>Type</>', '<fg=green;options=bold>Type</> <fg=gray;options=bold>/</> <fg=green;options=bold>Category</>');
            $types->each(fn($type) => $this->components->two_column_detail(($type['schema'] ? $type['schema'] . ' <fg=gray;options=bold>/</> ' : '') . $type['name'], $type['type'] . ' <fg=gray;options=bold>/</> ' . $type['category']));
            $this->new_line();
        }
    }
}