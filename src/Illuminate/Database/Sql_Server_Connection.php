<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Closure;
use Exception;
use Illuminate\Database\Query\Grammars\Sql_Server_Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Sql_Server_Processor;
use Illuminate\Database\Schema\Grammars\Sql_Server_Grammar as SchemaGrammar;
use Illuminate\Database\Schema\Sql_Server_Builder;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;
class Sql_Server_Connection extends Connection
{
    /**
     * {@inheritdoc}
     */
    public function get_driver_title(): string
    {
        return 'SQL Server';
    }
    /**
     * Execute a Closure within a transaction.
     *
     * @param  int  $attempts
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        for ($a = 1; $a <= $attempts; $a++) {
            if ($this->get_driver_name() === 'sqlsrv') {
                return parent::transaction($callback, $attempts);
            }
            $this->get_pdo()->exec('BEGIN TRAN');
            // We'll simply execute the given callback within a try / catch block
            // and if we catch any exception we can rollback the transaction
            // so that none of the changes are persisted to the database.
            try {
                $result = $callback($this);
                $this->get_pdo()->exec('COMMIT TRAN');
            } catch (Throwable $e) {
                $this->get_pdo()->exec('ROLLBACK TRAN');
                throw $e;
            }
            return $result;
        }
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
        return "0x{$hex}";
    }
    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     */
    protected function is_unique_constraint_error(Exception $exception): bool
    {
        return (bool) preg_match('#Cannot insert duplicate key row in object#i', $exception->get_message());
    }
    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\SqlServerGrammar
     */
    protected function get_default_query_grammar(): \Illuminate\Database\Query\Grammars\Grammar
    {
        return new Query_Grammar($this);
    }
    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Illuminate\Database\Schema\SqlServerBuilder
     */
    public function get_schema_builder(): \Illuminate\Database\Schema\Builder
    {
        if (is_null($this->schema_grammar)) {
            $this->use_default_schema_grammar();
        }
        return new Sql_Server_Builder($this);
    }
    /**
     * Get the default schema grammar instance.
     */
    protected function get_default_schema_grammar(): \Illuminate\Database\Schema\Grammars\Sql_Server_Grammar
    {
        return new Schema_Grammar($this);
    }
    /**
     * Get the schema state for the connection.
     *
     *
     * @throws \RuntimeException
     */
    public function get_schema_state(?Filesystem $files = null, ?callable $process_factory = null): never
    {
        throw new RuntimeException('Schema dumping is not supported when using SQL Server.');
    }
    /**
     * Get the default post processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\SqlServerProcessor
     */
    protected function get_default_post_processor(): \Illuminate\Database\Query\Processors\Processor
    {
        return new Sql_Server_Processor();
    }
}