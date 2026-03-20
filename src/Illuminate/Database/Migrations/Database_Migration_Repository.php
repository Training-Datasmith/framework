<?php

declare (strict_types=1);
namespace Illuminate\Database\Migrations;

use Illuminate\Database\Connection_Resolver_Interface as Resolver;
class Database_Migration_Repository implements Migration_Repository_Interface
{
    /**
     * The name of the database connection to use.
     *
     * @var string
     */
    protected $connection;
    /**
     * Create a new database migration repository instance.
     *
     * @param  string  $table
     */
    public function __construct(
        /**
         * The database connection resolver instance.
         */
        protected \Illuminate\Database\Connection_Resolver_Interface $resolver,
        /**
         * The name of the migration table.
         */
        protected $table
    )
    {
    }
    /**
     * Get the completed migrations.
     *
     * @return string[]
     */
    public function get_ran()
    {
        return $this->table()->order_by('batch', 'asc')->order_by('migration', 'asc')->pluck('migration')->all();
    }
    /**
     * Get the list of migrations.
     *
     * @param  int  $steps
     * @return array{id: int, migration: string, batch: int}[]
     */
    public function get_migrations($steps)
    {
        $query = $this->table()->where('batch', '>=', '1');
        return $query->order_by('batch', 'desc')->order_by('migration', 'desc')->limit($steps)->get()->all();
    }
    /**
     * Get the list of the migrations by batch number.
     *
     * @param  int  $batch
     * @return array{id: int, migration: string, batch: int}[]
     */
    public function get_migrations_by_batch($batch)
    {
        return $this->table()->where('batch', $batch)->order_by('migration', 'desc')->get()->all();
    }
    /**
     * Get the last migration batch.
     *
     * @return array{id: int, migration: string, batch: int}[]
     */
    public function get_last()
    {
        $query = $this->table()->where('batch', $this->get_last_batch_number());
        return $query->order_by('migration', 'desc')->get()->all();
    }
    /**
     * Get the completed migrations with their batch numbers.
     *
     * @return array<int, string>[]
     */
    public function get_migration_batches()
    {
        return $this->table()->order_by('batch', 'asc')->order_by('migration', 'asc')->pluck('batch', 'migration')->all();
    }
    /**
     * Log that a migration was run.
     *
     * @param  string  $file
     * @param  int  $batch
     */
    public function log($file, $batch): void
    {
        $record = ['migration' => $file, 'batch' => $batch];
        $this->table()->insert($record);
    }
    /**
     * Remove a migration from the log.
     *
     * @param  object{id?: int, migration: string, batch?: int}  $migration
     */
    public function delete($migration): void
    {
        $this->table()->where('migration', $migration->migration)->delete();
    }
    /**
     * Get the next migration batch number.
     *
     * @return int
     */
    public function get_next_batch_number(): int|float
    {
        return $this->get_last_batch_number() + 1;
    }
    /**
     * Get the last migration batch number.
     *
     * @return int
     */
    public function get_last_batch_number()
    {
        return $this->table()->max('batch');
    }
    /**
     * Create the migration repository data store.
     */
    public function create_repository(): void
    {
        $schema = $this->get_connection()->get_schema_builder();
        $schema->create($this->table, function ($table): void {
            // The migrations table is responsible for keeping track of which of the
            // migrations have actually run for the application. We'll create the
            // table to hold the migration file's path as well as the batch ID.
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
    }
    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repository_exists()
    {
        $schema = $this->get_connection()->get_schema_builder();
        return $schema->has_table($this->table);
    }
    /**
     * Delete the migration repository data store.
     */
    public function delete_repository(): void
    {
        $schema = $this->get_connection()->get_schema_builder();
        $schema->drop($this->table);
    }
    /**
     * Get a query builder for the migration table.
     */
    protected function table(): \Illuminate\Database\Query\Builder
    {
        return $this->get_connection()->table($this->table)->use_write_pdo();
    }
    /**
     * Get the connection resolver instance.
     */
    public function get_connection_resolver(): \Illuminate\Database\Connection_Resolver_Interface
    {
        return $this->resolver;
    }
    /**
     * Resolve the database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    public function get_connection()
    {
        return $this->resolver->connection($this->connection);
    }
    /**
     * Set the information source to gather data.
     *
     * @param  string  $name
     */
    public function set_source($name): void
    {
        $this->connection = $name;
    }
}