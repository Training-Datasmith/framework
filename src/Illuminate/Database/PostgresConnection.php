<?php

namespace Illuminate\Database;

use Exception;
use Illuminate\Database\Query\Grammars\PostgresGrammar as QueryGrammar;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as SchemaGrammar;
use Illuminate\Database\Schema\PostgresBuilder;
use Illuminate\Database\Schema\PostgresSchemaState;
use Illuminate\Filesystem\Filesystem;

class PostgresConnection extends Connection
{
    /**
     * {@inheritdoc}
     */
    public function getDriverTitle(): string
    {
        return 'PostgreSQL';
    }

    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param  string  $value
     */
    protected function escapeBinary($value): string
    {
        $hex = bin2hex($value);

        return "'\x{$hex}'::bytea";
    }

    /**
     * Escape a bool value for safe SQL embedding.
     *
     * @param  bool  $value
     */
    protected function escapeBool($value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     */
    protected function isUniqueConstraintError(Exception $exception): bool
    {
        return '23505' === $exception->getCode();
    }

    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): \Illuminate\Database\Query\Grammars\PostgresGrammar
    {
        return new QueryGrammar($this);
    }

    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder(): \Illuminate\Database\Schema\PostgresBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new PostgresBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): \Illuminate\Database\Schema\Grammars\PostgresGrammar
    {
        return new SchemaGrammar($this);
    }

    /**
     * Get the schema state for the connection.
     */
    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null): \Illuminate\Database\Schema\PostgresSchemaState
    {
        return new PostgresSchemaState($this, $files, $processFactory);
    }

    /**
     * Get the default post processor instance.
     */
    protected function getDefaultPostProcessor(): \Illuminate\Database\Query\Processors\PostgresProcessor
    {
        return new PostgresProcessor;
    }
}
