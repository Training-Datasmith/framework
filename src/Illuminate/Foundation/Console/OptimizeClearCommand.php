<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'optimize:clear')]
class OptimizeClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'optimize:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the cached bootstrap files';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->components->info('Clearing cached bootstrap files.');

        $exceptions = Collection::wrap(explode(',', $this->option('except') ?? ''))
            ->map(fn ($except): string => trim($except))
            ->filter()
            ->unique()
            ->flip();

        $tasks = Collection::wrap($this->getOptimizeClearTasks())
            ->reject(fn ($command, $key) => $exceptions->hasAny([$command, $key]))
            ->toArray();

        foreach ($tasks as $description => $command) {
            $this->components->task($description, fn (): bool => $this->callSilently($command) == 0);
        }

        $this->newLine();
    }

    /**
     * Get the commands that should be run to clear the "optimization" files.
     */
    public function getOptimizeClearTasks(): array
    {
        return [
            'config' => 'config:clear',
            'cache' => 'cache:clear',
            'compiled' => 'clear-compiled',
            'events' => 'event:clear',
            'routes' => 'route:clear',
            'views' => 'view:clear',
            ...ServiceProvider::$optimizeClearCommands,
        ];
    }

    /**
     * Get the console command arguments.
     */
    protected function getOptions(): array
    {
        return [
            ['except', 'e', InputOption::VALUE_OPTIONAL, 'The commands to skip'],
        ];
    }
}
