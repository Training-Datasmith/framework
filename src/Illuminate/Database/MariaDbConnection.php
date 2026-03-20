<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Database\Query\Grammars\Maria_Db_Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Maria_Db_Processor;
use Illuminate\Database\Schema\Grammars\Maria_Db_Grammar as SchemaGrammar;
use Illuminate\Database\Schema\Maria_Db_Builder;
use Illuminate\Database\Schema\Maria_Db_Schema_State;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
class Maria_Db_Connection extends My_Sql_Connection
{
    /**
     * {@inheritdoc}
     */
    public function get_driver_title(): string
    {
        return 'MariaDB';
    }
    /**
     * Determine if the connected database is a MariaDB database.
     */
    public function is_maria(): bool
    {
        return true;
    }
    /**
     * Get the server version for the connection.
     */
    public function get_server_version(): string
    {
        return Str::between(parent::get_server_version(), '5.5.5-', '-MariaDB');
    }
    /**
     * Get the default query grammar instance.
     */
    protected function get_default_query_grammar(): \Illuminate\Database\Query\Grammars\Maria_Db_Grammar
    {
        return new Query_Grammar($this);
    }
    /**
     * Get a schema builder instance for the connection.
     */
    public function get_schema_builder(): \Illuminate\Database\Schema\Maria_Db_Builder
    {
        if (is_null($this->schema_grammar)) {
            $this->use_default_schema_grammar();
        }
        return new Maria_Db_Builder($this);
    }
    /**
     * Get the default schema grammar instance.
     */
    protected function get_default_schema_grammar(): \Illuminate\Database\Schema\Grammars\Maria_Db_Grammar
    {
        return new Schema_Grammar($this);
    }
    /**
     * Get the schema state for the connection.
     */
    public function get_schema_state(?Filesystem $files = null, ?callable $process_factory = null): \Illuminate\Database\Schema\Maria_Db_Schema_State
    {
        return new Maria_Db_Schema_State($this, $files, $process_factory);
    }
    /**
     * Get the default post processor instance.
     */
    protected function get_default_post_processor(): \Illuminate\Database\Query\Processors\Maria_Db_Processor
    {
        return new Maria_Db_Processor();
    }
}