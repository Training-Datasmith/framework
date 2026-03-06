<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

#[AsCommand(name: 'view:cache')]
class ViewCacheCommand extends Command
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
        $this->callSilent('view:clear');

        $this->paths()->each(function (string $path): void {
            $prefix = $this->output->isVeryVerbose() ? '<fg=yellow;options=bold>DIR</> ' : '';

            $this->components->task($prefix.$path, null, OutputInterface::VERBOSITY_VERBOSE);

            $this->compileViews($this->bladeFilesIn([$path]));
        });

        $this->newLine();

        $this->components->info('Blade templates cached successfully.');
    }

    /**
     * Compile the given view files.
     *
     * @return void
     */
    protected function compileViews(Collection $views)
    {
        $compiler = $this->laravel['view']->getEngineResolver()->resolve('blade')->getCompiler();

        $views->map(function (SplFileInfo $file) use ($compiler): void {
            $this->components->task('    '.$file->getRelativePathname(), null, OutputInterface::VERBOSITY_VERY_VERBOSE);

            $compiler->compile($file->getRealPath());
        });

        if ($this->output->isVeryVerbose()) {
            $this->newLine();
        }
    }

    /**
     * Get the Blade files in the given path.
     */
    protected function bladeFilesIn(array $paths): \Illuminate\Support\Collection
    {
        $extensions = (new Collection($this->laravel['view']->getExtensions()))
            ->filter(fn ($value): bool => $value === 'blade')
            ->keys()
            ->map(fn ($extension): string => "*.{$extension}")
            ->all();

        return new Collection(
            Finder::create()
                ->in($paths)
                ->exclude('vendor')
                ->name($extensions)
                ->files()
        );
    }

    /**
     * Get all of the possible view paths.
     */
    protected function paths(): \Illuminate\Support\Collection
    {
        $finder = $this->laravel['view']->getFinder();

        return (new Collection($finder->getPaths()))->merge(
            (new Collection($finder->getHints()))->flatten()
        );
    }
}
