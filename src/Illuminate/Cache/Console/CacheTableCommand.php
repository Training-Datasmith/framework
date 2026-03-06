<?php

declare(strict_types=1);

namespace Illuminate\Cache\Console;

use Illuminate\Console\MigrationGeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:cache-table', aliases: ['cache:table'])]
class CacheTableCommand extends MigrationGeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:cache-table';

    /**
     * The console command name aliases.
     *
     * @var array
     */
    protected $aliases = ['cache:table'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a migration for the cache database table';

    /**
     * Get the migration table name.
     */
    protected function migrationTableName(): string
    {
        return 'cache';
    }

    /**
     * Get the path to the migration stub file.
     */
    protected function migrationStubFile(): string
    {
        return __DIR__.'/stubs/cache.stub';
    }
}
