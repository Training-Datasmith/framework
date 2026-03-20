<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Exception;
use Illuminate\Database\Query\Grammars\Sq_Lite_Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Sq_Lite_Processor;
use Illuminate\Database\Schema\Grammars\Sq_Lite_Grammar as SchemaGrammar;
use Illuminate\Database\Schema\Sq_Lite_Builder;
use Illuminate\Database\Schema\Sqlite_Schema_State;
use Illuminate\Filesystem\Filesystem;
class Sq_Lite_Connection extends Connection
{
    /**
     * {@inheritdoc}
     */
    public function get_driver_title(): string
    {
        return 'SQLite';
    }
    /**
     * Run the statement to start a new transaction.
     *
     * @return void
     */
    protected function execute_begin_transaction_statement()
    {
        $this->get_pdo()->begin_transaction();
    }
    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param  string  $value
     * @return string
     */
    protected function escape_binary($value)
    {
        $hex = bin2hex($value);
        return "x'{$hex}'";
    }
    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     */
    protected function is_unique_constraint_error(Exception $exception): bool
    {
        return (bool) preg_match('#(column(s)? .* (is|are) not unique|UNIQUE constraint failed: .*)#i', $exception->get_message());
    }
    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\SQLiteGrammar
     */
    protected function get_default_query_grammar(): \Illuminate\Database\Query\Grammars\Grammar
    {
        return new Query_Grammar($this);
    }
    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Illuminate\Database\Schema\SQLiteBuilder
     */
    public function get_schema_builder(): \Illuminate\Database\Schema\Builder
    {
        if (is_null($this->schema_grammar)) {
            $this->use_default_schema_grammar();
        }
        return new Sq_Lite_Builder($this);
    }
    /**
     * Get the default schema grammar instance.
     */
    protected function get_default_schema_grammar(): \Illuminate\Database\Schema\Grammars\Sq_Lite_Grammar
    {
        return new Schema_Grammar($this);
    }
    /**
     * Get the schema state for the connection.
     *
     *
     * @throws \RuntimeException
     */
    public function get_schema_state(?Filesystem $files = null, ?callable $process_factory = null): \Illuminate\Database\Schema\Sqlite_Schema_State
    {
        return new Sqlite_Schema_State($this, $files, $process_factory);
    }
    /**
     * Get the default post processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\SQLiteProcessor
     */
    protected function get_default_post_processor(): \Illuminate\Database\Query\Processors\Processor
    {
        return new Sq_Lite_Processor();
    }
}