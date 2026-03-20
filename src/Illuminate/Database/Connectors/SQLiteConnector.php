<?php

declare (strict_types=1);
namespace Illuminate\Database\Connectors;

use Illuminate\Database\Sq_Lite_Database_Does_Not_Exist_Exception;
class Sq_Lite_Connector extends Connector implements Connector_Interface
{
    /**
     * Establish a database connection.
     *
     * @return \PDO
     */
    public function connect(array $config)
    {
        $options = $this->get_options($config);
        $path = $this->parse_database_path($config['database']);
        $connection = $this->create_connection("sqlite:{$path}", $config, $options);
        $this->configure_pragmas($connection, $config);
        $this->configure_foreign_key_constraints($connection, $config);
        $this->configure_busy_timeout($connection, $config);
        $this->configure_journal_mode($connection, $config);
        $this->configure_synchronous($connection, $config);
        return $connection;
    }
    /**
     * Get the absolute database path.
     *
     *
     * @throws \Illuminate\Database\SQLiteDatabaseDoesNotExistException
     */
    protected function parse_database_path(string $path): string
    {
        $database = $path;
        // SQLite supports "in-memory" databases that only last as long as the owning
        // connection does. These are useful for tests or for short lifetime store
        // querying. In-memory databases shall be anonymous (:memory:) or named.
        if ($path === ':memory:' || str_contains($path, '?mode=memory') || str_contains($path, '&mode=memory')) {
            return $path;
        }
        $path = realpath($path) ?: realpath(base_path($path));
        // Here we'll verify that the SQLite database exists before going any further
        // as the developer probably wants to know if the database exists and this
        // SQLite driver will not throw any exception if it does not by default.
        if ($path === false) {
            throw new Sq_Lite_Database_Does_Not_Exist_Exception($database);
        }
        return $path;
    }
    /**
     * Set miscellaneous user-configured pragmas.
     *
     * @param  \PDO  $connection
     */
    protected function configure_pragmas($connection, array $config): void
    {
        if (!isset($config['pragmas'])) {
            return;
        }
        foreach ($config['pragmas'] as $pragma => $value) {
            $connection->prepare("pragma {$pragma} = {$value}")->execute();
        }
    }
    /**
     * Enable or disable foreign key constraints if configured.
     *
     * @param  \PDO  $connection
     */
    protected function configure_foreign_key_constraints($connection, array $config): void
    {
        if (!isset($config['foreign_key_constraints'])) {
            return;
        }
        $foreign_keys = $config['foreign_key_constraints'] ? 1 : 0;
        $connection->prepare("pragma foreign_keys = {$foreign_keys}")->execute();
    }
    /**
     * Set the busy timeout if configured.
     *
     * @param  \PDO  $connection
     */
    protected function configure_busy_timeout($connection, array $config): void
    {
        if (!isset($config['busy_timeout'])) {
            return;
        }
        $connection->prepare("pragma busy_timeout = {$config['busy_timeout']}")->execute();
    }
    /**
     * Set the journal mode if configured.
     *
     * @param  \PDO  $connection
     */
    protected function configure_journal_mode($connection, array $config): void
    {
        if (!isset($config['journal_mode'])) {
            return;
        }
        $connection->prepare("pragma journal_mode = {$config['journal_mode']}")->execute();
    }
    /**
     * Set the synchronous mode if configured.
     *
     * @param  \PDO  $connection
     */
    protected function configure_synchronous($connection, array $config): void
    {
        if (!isset($config['synchronous'])) {
            return;
        }
        $connection->prepare("pragma synchronous = {$config['synchronous']}")->execute();
    }
}