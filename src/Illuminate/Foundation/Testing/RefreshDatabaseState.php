<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

class Refresh_Database_State
{
    /**
     * The current SQLite in-memory database connections.
     *
     * @var array<string, \PDO>
     */
    public static $in_memory_connections = [];
    /**
     * Indicates if the test database has been migrated.
     *
     * @var bool
     */
    public static $migrated = false;
    /**
     * Indicates if a lazy refresh hook has been invoked.
     *
     * @var bool
     */
    public static $lazily_refreshed = false;
}