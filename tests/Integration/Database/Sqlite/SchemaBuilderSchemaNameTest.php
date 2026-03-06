<?php

declare(strict_types=1);

namespace Illuminate\Tests\Integration\Database\Sqlite;

use Orchestra\Testbench\Attributes\RequiresDatabase;

#[RequiresDatabase('sqlite')]
class SchemaBuilderSchemaNameTest extends \Illuminate\Tests\Integration\Database\SchemaBuilderSchemaNameTest
{
}
