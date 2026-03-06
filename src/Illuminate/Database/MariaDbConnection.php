<?php

declare(strict_types=1);

namespace Illuminate\Database;

use Illuminate\Database\Query\Grammars\MariaDbGrammar as QueryGrammar;
use Illuminate\Database\Query\Processors\MariaDbProcessor;
use Illuminate\Database\Schema\Grammars\MariaDbGrammar as SchemaGrammar;
use Illuminate\Database\Schema\MariaDbBuilder;
use Illuminate\Database\Schema\MariaDbSchemaState;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MariaDbConnection extends MySqlConnection
{
    /**
     * {@inheritdoc}
     */
    public function getDriverTitle(): string
    {
        return 'MariaDB';
    }

    /**
     * Determine if the connected database is a MariaDB database.
     */
    public function isMaria(): bool
    {
        return true;
    }

    /**
     * Get the server version for the connection.
     */
    public function getServerVersion(): string
    {
        return Str::between(parent::getServerVersion(), '5.5.5-', '-MariaDB');
    }

    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): \Illuminate\Database\Query\Grammars\MariaDbGrammar
    {
        return new QueryGrammar($this);
    }

    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder(): \Illuminate\Database\Schema\MariaDbBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new MariaDbBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): \Illuminate\Database\Schema\Grammars\MariaDbGrammar
    {
        return new SchemaGrammar($this);
    }

    /**
     * Get the schema state for the connection.
     */
    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null): \Illuminate\Database\Schema\MariaDbSchemaState
    {
        return new MariaDbSchemaState($this, $files, $processFactory);
    }

    /**
     * Get the default post processor instance.
     */
    protected function getDefaultPostProcessor(): \Illuminate\Database\Query\Processors\MariaDbProcessor
    {
        return new MariaDbProcessor();
    }
}
