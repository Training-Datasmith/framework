<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Service_Provider;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'optimize')]
class Optimize_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'optimize';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache framework bootstrap, configuration, and metadata to increase performance';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->components->info('Caching framework bootstrap, configuration, and metadata.');
        $exceptions = Collection::wrap(explode(',', $this->option('except') ?? ''))->map(fn($except): string => trim($except))->filter()->unique()->flip();
        $tasks = Collection::wrap($this->get_optimize_tasks())->reject(fn($command, $key) => $exceptions->has_any([$command, $key]))->to_array();
        foreach ($tasks as $description => $command) {
            $this->components->task($description, fn(): bool => $this->call_silently($command) == 0);
        }
        $this->new_line();
    }
    /**
     * Get the commands that should be run to optimize the framework.
     */
    protected function get_optimize_tasks(): array
    {
        return ['config' => 'config:cache', 'events' => 'event:cache', 'routes' => 'route:cache', 'views' => 'view:cache', ...Service_Provider::$optimize_commands];
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['except', 'e', Input_Option::VALUE_OPTIONAL, 'Do not run the commands matching the key or name']];
    }
}