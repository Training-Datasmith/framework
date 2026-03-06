<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Database;

use Illuminate\Foundation\Testing\DatabaseMigrations;

class EloquentTransactionWithAfterCommitUsingDatabaseMigrationsTest extends DatabaseTestCase
{
    use EloquentTransactionWithAfterCommitTests;
    use DatabaseMigrations;
}
