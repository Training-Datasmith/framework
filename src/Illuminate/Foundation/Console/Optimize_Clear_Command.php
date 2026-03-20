<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Service_Provider;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'optimize:clear')]
class Optimize_Clear_Command extends Command
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
        $exceptions = Collection::wrap(explode(',', $this->option('except') ?? ''))->map(fn($except): string => trim($except))->filter()->unique()->flip();
        $tasks = Collection::wrap($this->get_optimize_clear_tasks())->reject(fn($command, $key) => $exceptions->has_any([$command, $key]))->to_array();
        foreach ($tasks as $description => $command) {
            $this->components->task($description, fn(): bool => $this->call_silently($command) == 0);
        }
        $this->new_line();
    }
    /**
     * Get the commands that should be run to clear the "optimization" files.
     */
    public function get_optimize_clear_tasks(): array
    {
        return ['config' => 'config:clear', 'cache' => 'cache:clear', 'compiled' => 'clear-compiled', 'events' => 'event:clear', 'routes' => 'route:clear', 'views' => 'view:clear', ...Service_Provider::$optimize_clear_commands];
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['except', 'e', Input_Option::VALUE_OPTIONAL, 'The commands to skip']];
    }
}