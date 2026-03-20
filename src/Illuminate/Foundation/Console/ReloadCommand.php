<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Service_Provider;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'reload')]
class Reload_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'reload';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reload running services';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->components->info('Reloading services.');
        $exceptions = Collection::wrap(explode(',', $this->option('except') ?? ''))->map(fn($except): string => trim($except))->filter()->unique()->flip();
        $tasks = Collection::wrap($this->get_reload_tasks())->reject(fn($command, $key) => $exceptions->has_any([$command, $key]))->to_array();
        foreach ($tasks as $description => $command) {
            $this->components->task($description, fn(): bool => $this->call_silently($command) == 0);
        }
        $this->new_line();
    }
    /**
     * Get the commands that should be reloaded.
     */
    public function get_reload_tasks(): array
    {
        return ['queue' => 'queue:restart', 'schedule' => 'schedule:interrupt', ...Service_Provider::$reload_commands];
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['except', 'e', Input_Option::VALUE_OPTIONAL, 'The commands to skip']];
    }
}