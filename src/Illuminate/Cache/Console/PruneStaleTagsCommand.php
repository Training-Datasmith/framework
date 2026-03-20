<?php

declare (strict_types=1);
namespace Illuminate\Cache\Console;

use Illuminate\Cache\Cache_Manager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Argument;
#[As_Command(name: 'cache:prune-stale-tags')]
class Prune_Stale_Tags_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cache:prune-stale-tags';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune stale cache tags from the cache (Redis only)';
    /**
     * Execute the console command.
     */
    public function handle(Cache_Manager $cache): void
    {
        $cache = $cache->store($this->argument('store'));
        if (method_exists($cache->get_store(), 'flushStaleTags')) {
            $cache->flush_stale_tags();
        }
        $this->components->info('Stale cache tags pruned successfully.');
    }
    /**
     * Get the console command arguments.
     */
    protected function get_arguments(): array
    {
        return [['store', Input_Argument::OPTIONAL, 'The name of the store you would like to prune tags from']];
    }
}