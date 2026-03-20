<?php

declare (strict_types=1);
namespace Illuminate\Cache\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'cache:forget')]
class Forget_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'cache:forget {key : The key to remove} {store? : The store to remove the key from}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove an item from the cache';
    /**
     * Create a new cache clear command instance.
     */
    public function __construct(
        /**
         * The cache manager instance.
         */
        protected \Illuminate\Cache\Cache_Manager $cache
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->cache->store($this->argument('store'))->forget($this->argument('key'));
        $this->components->info('The [' . $this->argument('key') . '] key has been removed from the cache.');
    }
}