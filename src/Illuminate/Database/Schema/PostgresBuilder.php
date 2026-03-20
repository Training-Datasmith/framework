<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Database\Concerns\Parses_Search_Path;
class Postgres_Builder extends Builder
{
    use Parses_Search_Path;
    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function drop_all_tables()
    {
        $tables = [];
        $excluded_tables = $this->connection->get_config('dont_drop') ?? ['spatial_ref_sys'];
        foreach ($this->get_tables($this->get_current_schema_listing()) as $table) {
            if (empty(array_intersect([$table['name'], $table['schema_qualified_name']], $excluded_tables))) {
                $tables[] = $table['schema_qualified_name'];
            }
        }
        if (empty($tables)) {
            return;
        }
        $this->connection->statement($this->grammar->compile_drop_all_tables($tables));
    }
    /**
     * Drop all views from the database.
     *
     * @return void
     */
    public function drop_all_views()
    {
        $views = array_column($this->get_views($this->get_current_schema_listing()), 'schema_qualified_name');
        if (empty($views)) {
            return;
        }
        $this->connection->statement($this->grammar->compile_drop_all_views($views));
    }
    /**
     * Drop all types from the database.
     *
     * @return void
     */
    public function drop_all_types()
    {
        $types = [];
        $domains = [];
        foreach ($this->get_types($this->get_current_schema_listing()) as $type) {
            if (!$type['implicit']) {
                if ($type['type'] === 'domain') {
                    $domains[] = $type['schema_qualified_name'];
                } else {
                    $types[] = $type['schema_qualified_name'];
                }
            }
        }
        if (!empty($types)) {
            $this->connection->statement($this->grammar->compile_drop_all_types($types));
        }
        if (!empty($domains)) {
            $this->connection->statement($this->grammar->compile_drop_all_domains($domains));
        }
    }
    /**
     * Get the current schemas for the connection.
     *
     * @return string[]
     */
    public function get_current_schema_listing(): null
    {
        return array_map(fn($schema) => $schema === '$user' ? $this->connection->get_config('username') : $schema, $this->parse_search_path(($this->connection->get_config('search_path') ?: $this->connection->get_config('schema')) ?: 'public'));
    }
}