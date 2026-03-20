<?php

declare (strict_types=1);
namespace Illuminate\Cache\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'cache:clear')]
class Clear_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cache:clear';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush the application cache';
    /**
     * Create a new cache clear command instance.
     */
    public function __construct(
        /**
         * The cache manager instance.
         */
        protected \Illuminate\Cache\Cache_Manager $cache,
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->laravel['events']->dispatch('cache:clearing', [$this->argument('store'), $this->tags()]);
        $successful = $this->cache()->flush();
        $this->flush_facades();
        if (!$successful) {
            $this->components->error('Failed to clear cache. Make sure you have the appropriate permissions.');
            return self::FAILURE;
        }
        $this->laravel['events']->dispatch('cache:cleared', [$this->argument('store'), $this->tags()]);
        $this->components->info('Application cache cleared successfully.');
        return self::SUCCESS;
    }
    /**
     * Flush the real-time facades stored in the cache directory.
     */
    public function flush_facades(): void
    {
        if (!$this->files->exists($storage_path = storage_path('framework/cache'))) {
            return;
        }
        foreach ($this->files->files($storage_path) as $file) {
            if (preg_match('/facade-.*\.php$/', $file)) {
                $this->files->delete($file);
            }
        }
    }
    /**
     * Get the cache instance for the command.
     *
     * @return \Illuminate\Cache\Repository
     */
    protected function cache()
    {
        $cache = $this->cache->store($this->argument('store'));
        return empty($this->tags()) ? $cache : $cache->tags($this->tags());
    }
    /**
     * Get the tags passed to the command.
     */
    protected function tags(): array
    {
        return array_filter(explode(',', $this->option('tags') ?? ''));
    }
    /**
     * Get the console command arguments.
     */
    protected function get_arguments(): array
    {
        return [['store', Input_Argument::OPTIONAL, 'The name of the store you would like to clear']];
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['tags', null, Input_Option::VALUE_OPTIONAL, 'The cache tags you would like to clear', null]];
    }
}