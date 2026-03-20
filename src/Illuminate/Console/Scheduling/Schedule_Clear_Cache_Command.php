<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'schedule:clear-cache')]
class Schedule_Clear_Cache_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule:clear-cache';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the cached mutex files created by scheduler';
    /**
     * Execute the console command.
     */
    public function handle(Schedule $schedule): void
    {
        $mutex_cleared = false;
        foreach ($schedule->events() as $event) {
            if ($event->mutex->exists($event)) {
                $this->components->info(sprintf('Deleting mutex for [%s]', $event->command));
                $event->mutex->forget($event);
                $mutex_cleared = true;
            }
        }
        if (!$mutex_cleared) {
            $this->components->info('No mutex files were found.');
        }
    }
}