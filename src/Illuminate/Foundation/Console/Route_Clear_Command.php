<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'route:clear')]
class Route_Clear_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'route:clear';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the route cache file';
    /**
     * Create a new route clear command instance.
     */
    public function __construct(
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
    public function handle(): void
    {
        $this->files->delete($this->laravel->get_cached_routes_path());
        $this->components->info('Route cache cleared successfully.');
    }
}