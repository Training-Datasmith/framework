<?php

declare (strict_types=1);
namespace Illuminate\Cache\Console;

use Illuminate\Console\Migration_Generator_Command;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'make:cache-table', aliases: ['cache:table'])]
class Cache_Table_Command extends Migration_Generator_Command
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
    protected function migration_table_name(): string
    {
        return 'cache';
    }
    /**
     * Get the path to the migration stub file.
     */
    protected function migration_stub_file(): string
    {
        return __DIR__ . '/stubs/cache.stub';
    }
}