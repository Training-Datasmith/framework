<?php

declare (strict_types=1);
namespace Illuminate\Database\Migrations;

interface Migration_Repository_Interface
{
    /**
     * Get the completed migrations.
     *
     * @return string[]
     */
    public function get_ran();
    /**
     * Get the list of migrations.
     *
     * @param  int  $steps
     * @return array{id: int, migration: string, batch: int}[]
     */
    public function get_migrations($steps);
    /**
     * Get the list of the migrations by batch.
     *
     * @param  int  $batch
     * @return array{id: int, migration: string, batch: int}[]
     */
    public function get_migrations_by_batch($batch);
    /**
     * Get the last migration batch.
     *
     * @return array{id: int, migration: string, batch: int}[]
     */
    public function get_last();
    /**
     * Get the completed migrations with their batch numbers.
     *
     * @return array<int, string>[]
     */
    public function get_migration_batches();
    /**
     * Log that a migration was run.
     *
     * @param  string  $file
     * @param  int  $batch
     * @return void
     */
    public function log($file, $batch);
    /**
     * Remove a migration from the log.
     *
     * @param  objectt{id?: int, migration: string, batch?: int}  $migration
     * @return void
     */
    public function delete($migration);
    /**
     * Get the next migration batch number.
     *
     * @return int
     */
    public function get_next_batch_number();
    /**
     * Create the migration repository data store.
     *
     * @return void
     */
    public function create_repository();
    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repository_exists();
    /**
     * Delete the migration repository data store.
     *
     * @return void
     */
    public function delete_repository();
    /**
     * Set the information source to gather data.
     *
     * @param  string  $name
     * @return void
     */
    public function set_source($name);
}