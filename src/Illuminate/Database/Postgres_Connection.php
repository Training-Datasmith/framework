<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Exception;
use Illuminate\Database\Query\Grammars\Postgres_Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Postgres_Processor;
use Illuminate\Database\Schema\Grammars\Postgres_Grammar as SchemaGrammar;
use Illuminate\Database\Schema\Postgres_Builder;
use Illuminate\Database\Schema\Postgres_Schema_State;
use Illuminate\Filesystem\Filesystem;
class Postgres_Connection extends Connection
{
    /**
     * {@inheritdoc}
     */
    public function get_driver_title(): string
    {
        return 'PostgreSQL';
    }
    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param  string  $value
     */
    protected function escape_binary($value): string
    {
        $hex = bin2hex($value);
        return "'\\x{$hex}'::bytea";
    }
    /**
     * Escape a bool value for safe SQL embedding.
     *
     * @param  bool  $value
     */
    protected function escape_bool($value): string
    {
        return $value ? 'true' : 'false';
    }
    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     */
    protected function is_unique_constraint_error(Exception $exception): bool
    {
        return '23505' === $exception->get_code();
    }
    /**
     * Get the default query grammar instance.
     */
    protected function get_default_query_grammar(): \Illuminate\Database\Query\Grammars\Postgres_Grammar
    {
        return new Query_Grammar($this);
    }
    /**
     * Get a schema builder instance for the connection.
     */
    public function get_schema_builder(): \Illuminate\Database\Schema\Postgres_Builder
    {
        if (is_null($this->schema_grammar)) {
            $this->use_default_schema_grammar();
        }
        return new Postgres_Builder($this);
    }
    /**
     * Get the default schema grammar instance.
     */
    protected function get_default_schema_grammar(): \Illuminate\Database\Schema\Grammars\Postgres_Grammar
    {
        return new Schema_Grammar($this);
    }
    /**
     * Get the schema state for the connection.
     */
    public function get_schema_state(?Filesystem $files = null, ?callable $process_factory = null): \Illuminate\Database\Schema\Postgres_Schema_State
    {
        return new Postgres_Schema_State($this, $files, $process_factory);
    }
    /**
     * Get the default post processor instance.
     */
    protected function get_default_post_processor(): \Illuminate\Database\Query\Processors\Postgres_Processor
    {
        return new Postgres_Processor();
    }
}