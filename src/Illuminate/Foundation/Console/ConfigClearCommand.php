<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'config:clear')]
class Config_Clear_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'config:clear';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the configuration cache file';
    /**
     * Create a new config clear command instance.
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
        $this->files->delete($this->laravel->get_cached_config_path());
        $this->components->info('Configuration cache cleared successfully.');
    }
}