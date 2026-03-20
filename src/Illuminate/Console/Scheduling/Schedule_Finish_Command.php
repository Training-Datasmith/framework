<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Console\Command;
use Illuminate\Console\Events\Scheduled_Background_Task_Finished;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'schedule:finish')]
class Schedule_Finish_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'schedule:finish {id} {code=0}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle the completion of a scheduled command';
    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;
    /**
     * Execute the console command.
     */
    public function handle(Schedule $schedule): void
    {
        (new Collection($schedule->events()))->filter(fn($value): bool => $value->mutex_name() == $this->argument('id'))->each(function ($event): void {
            $event->finish($this->laravel, $this->argument('code'));
            $this->laravel->make(Dispatcher::class)->dispatch(new Scheduled_Background_Task_Finished($event));
        });
    }
}