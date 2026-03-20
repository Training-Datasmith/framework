<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'clear-compiled')]
class Clear_Compiled_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'clear-compiled';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the compiled class file';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (is_file($services_path = $this->laravel->get_cached_services_path())) {
            @unlink($services_path);
        }
        if (is_file($packages_path = $this->laravel->get_cached_packages_path())) {
            @unlink($packages_path);
        }
        $this->components->info('Compiled services and packages files removed successfully.');
    }
}