<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

class My_Sql_Builder extends Builder
{
    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function drop_all_tables()
    {
        $tables = $this->get_table_listing($this->get_current_schema_listing());
        if (empty($tables)) {
            return;
        }
        $this->disable_foreign_key_constraints();
        try {
            $this->connection->statement($this->grammar->compile_drop_all_tables($tables));
        } finally {
            $this->enable_foreign_key_constraints();
        }
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
     * Get the names of current schemas for the connection.
     *
     * @return string[]|null
     */
    public function get_current_schema_listing(): null
    {
        return [$this->connection->get_database_name()];
    }
}