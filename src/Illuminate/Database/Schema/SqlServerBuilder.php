<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Support\Arr;
class Sql_Server_Builder extends Builder
{
    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function drop_all_tables()
    {
        $this->connection->statement($this->grammar->compile_drop_all_foreign_keys());
        $this->connection->statement($this->grammar->compile_drop_all_tables());
    }
    /**
     * Drop all views from the database.
     *
     * @return void
     */
    public function drop_all_views()
    {
        $this->connection->statement($this->grammar->compile_drop_all_views());
    }
    /**
     * Get the default schema name for the connection.
     *
     * @return string|null
     */
    public function get_current_schema_name()
    {
        return Arr::first($this->get_schemas(), fn($schema): bool => $schema['default'])['name'];
    }
}