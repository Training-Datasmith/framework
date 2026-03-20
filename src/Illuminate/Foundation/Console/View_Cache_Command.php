<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Spl_File_Info;
#[As_Command(name: 'view:cache')]
class View_Cache_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'view:cache';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Compile all of the application's Blade templates";
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->call_silent('view:clear');
        $this->paths()->each(function (string $path): void {
            $prefix = $this->output->is_very_verbose() ? '<fg=yellow;options=bold>DIR</> ' : '';
            $this->components->task($prefix . $path, null, Output_Interface::VERBOSITY_VERBOSE);
            $this->compile_views($this->blade_files_in([$path]));
        });
        $this->new_line();
        $this->components->info('Blade templates cached successfully.');
    }
    /**
     * Compile the given view files.
     *
     * @return void
     */
    protected function compile_views(Collection $views)
    {
        $compiler = $this->laravel['view']->get_engine_resolver()->resolve('blade')->get_compiler();
        $views->map(function (Spl_File_Info $file) use ($compiler): void {
            $this->components->task('    ' . $file->get_relative_pathname(), null, Output_Interface::VERBOSITY_VERY_VERBOSE);
            $compiler->compile($file->get_real_path());
        });
        if ($this->output->is_very_verbose()) {
            $this->new_line();
        }
    }
    /**
     * Get the Blade files in the given path.
     */
    protected function blade_files_in(array $paths): \Illuminate\Support\Collection
    {
        $extensions = (new Collection($this->laravel['view']->get_extensions()))->filter(fn($value): bool => $value === 'blade')->keys()->map(fn($extension): string => "*.{$extension}")->all();
        return new Collection(Finder::create()->in($paths)->exclude('vendor')->name($extensions)->files());
    }
    /**
     * Get all of the possible view paths.
     */
    protected function paths(): \Illuminate\Support\Collection
    {
        $finder = $this->laravel['view']->get_finder();
        return (new Collection($finder->get_paths()))->merge((new Collection($finder->get_hints()))->flatten());
    }
}