<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Console\Application;
use Illuminate\Console\Command;
use function Laravel\Prompts\select;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'schedule:test')]
class Schedule_Test_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'schedule:test {--name= : The name of the scheduled command to run}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a scheduled command';
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(Schedule $schedule)
    {
        $php_binary = Application::php_binary();
        $commands = $schedule->events();
        $command_names = [];
        foreach ($commands as $command) {
            $command_names[] = $command->command ?? $command->get_summary_for_display();
        }
        if (empty($command_names)) {
            return $this->components->info('No scheduled commands have been defined.');
        }
        if (!empty($name = $this->option('name'))) {
            $command_binary = $php_binary . ' ' . Application::artisan_binary();
            $matches = array_filter($command_names, fn($command_name): bool => trim(str_replace($command_binary, '', $command_name)) === $name);
            if (count($matches) !== 1) {
                $this->components->info('No matching scheduled command found.');
                return;
            }
            $index = key($matches);
        } else {
            $index = $this->get_selected_command_by_index($command_names);
        }
        $event = $commands[$index];
        $summary = $event->get_summary_for_display();
        $command = $event instanceof Callback_Event ? $summary : trim(str_replace($php_binary, '', $event->command));
        $description = sprintf('Running [%s]%s', $command, $event->run_in_background ? ' normally in background' : '');
        $event->run_in_background = false;
        $this->components->task($description, fn() => $event->run($this->laravel));
        if (!$event instanceof Callback_Event) {
            $this->components->bullet_list([$event->get_summary_for_display()]);
        }
        $this->new_line();
    }
    /**
     * Get the selected command name by index.
     *
     * @return int
     */
    protected function get_selected_command_by_index(array $command_names): int|string|false
    {
        if (count($command_names) !== count(array_unique($command_names))) {
            // Some commands (likely closures) have the same name, append unique indexes to each one...
            $unique_command_names = array_map(fn($index, int|string $value): string => "{$value} [{$index}]", array_keys($command_names), $command_names);
            $selected_command = select('Which command would you like to run?', $unique_command_names);
            preg_match('/\[(\d+)\]/', $selected_command, $choice);
            return (int) $choice[1];
        }
        return array_search(select('Which command would you like to run?', $command_names), $command_names);
    }
}