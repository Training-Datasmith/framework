<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Exception;
use Illuminate\Console\Application;
use Illuminate\Console\Command;
use Illuminate\Console\Events\Scheduled_Task_Failed;
use Illuminate\Console\Events\Scheduled_Task_Finished;
use Illuminate\Console\Events\Scheduled_Task_Skipped;
use Illuminate\Console\Events\Scheduled_Task_Starting;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\Exception_Handler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;
use Symfony\Component\Console\Attribute\As_Command;
use Throwable;
#[As_Command(name: 'schedule:run')]
class Schedule_Run_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:run {--whisper : Do not output message indicating that no jobs were ready to run}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled commands';
    /**
     * The schedule instance.
     *
     * @var \Illuminate\Console\Scheduling\Schedule
     */
    protected $schedule;
    /**
     * The 24 hour timestamp this scheduler command started running.
     *
     * @var \Illuminate\Support\Carbon
     */
    protected $started_at;
    /**
     * Check if any events ran.
     *
     * @var bool
     */
    protected $events_ran = false;
    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $dispatcher;
    /**
     * The exception handler.
     *
     * @var \Illuminate\Contracts\Debug\ExceptionHandler
     */
    protected $handler;
    /**
     * The cache store implementation.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;
    /**
     * The PHP binary used by the command.
     *
     * @var string
     */
    protected $php_binary;
    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        $this->started_at = Date::now();
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(Schedule $schedule, Dispatcher $dispatcher, Cache $cache, Exception_Handler $handler): void
    {
        $this->schedule = $schedule;
        $this->dispatcher = $dispatcher;
        $this->cache = $cache;
        $this->handler = $handler;
        $this->php_binary = Application::php_binary();
        $events = $this->schedule->due_events($this->laravel);
        if ($events->contains->is_repeatable()) {
            $this->clear_interrupt_signal();
        }
        foreach ($events as $event) {
            if (!$event->filters_pass($this->laravel)) {
                $this->dispatcher->dispatch(new Scheduled_Task_Skipped($event));
                continue;
            }
            if (!$this->events_ran) {
                $this->new_line();
            }
            if ($event->on_one_server) {
                $this->run_single_server_event($event);
            } else {
                $this->run_event($event);
            }
            $this->events_ran = true;
        }
        if ($events->contains->is_repeatable()) {
            $this->repeat_events($events->filter->is_repeatable());
        }
        if (!$this->events_ran) {
            if (!$this->option('whisper')) {
                $this->components->info('No scheduled commands are ready to run.');
            }
        } else {
            $this->new_line();
        }
    }
    /**
     * Run the given single server event.
     *
     * @param  \Illuminate\Console\Scheduling\Event  $event
     * @return void
     */
    protected function run_single_server_event($event)
    {
        if ($this->schedule->server_should_run($event, $this->started_at)) {
            $this->run_event($event);
        } else {
            $this->components->info(sprintf('Skipping [%s] because the command already ran on another server.', $event->get_summary_for_display()));
        }
    }
    /**
     * Run the given event.
     *
     * @param  \Illuminate\Console\Scheduling\Event  $event
     * @return void
     */
    protected function run_event($event)
    {
        $summary = $event->get_summary_for_display();
        $command = $event instanceof Callback_Event ? $summary : trim(str_replace($this->php_binary, '', $event->command));
        $description = sprintf('<fg=gray>%s</> Running [%s]%s', Carbon::now()->format('Y-m-d H:i:s'), $command, $event->run_in_background ? ' in background' : '');
        $this->components->task($description, function () use ($event): bool {
            $this->dispatcher->dispatch(new Scheduled_Task_Starting($event));
            $start = microtime(true);
            try {
                $event->run($this->laravel);
                $this->dispatcher->dispatch(new Scheduled_Task_Finished($event, round(microtime(true) - $start, 2)));
                $this->events_ran = true;
                if ($event->exit_code != 0 && !$event->run_in_background) {
                    throw new Exception("Scheduled command [{$event->command}] failed with exit code [{$event->exit_code}].");
                }
            } catch (Throwable $e) {
                $this->dispatcher->dispatch(new Scheduled_Task_Failed($event, $e));
                $this->handler->report($e);
            }
            return $event->exit_code == 0;
        });
        if (!$event instanceof Callback_Event) {
            $this->components->bullet_list([$event->get_summary_for_display()]);
        }
    }
    /**
     * Run the given repeating events.
     *
     * @param  \Illuminate\Support\Collection<\Illuminate\Console\Scheduling\Event>  $events
     * @return void
     */
    protected function repeat_events($events)
    {
        $has_entered_maintenance_mode = false;
        while (Date::now()->lte($this->started_at->end_of_minute())) {
            foreach ($events as $event) {
                if ($this->should_interrupt()) {
                    return;
                }
                if (!$event->should_repeat_now()) {
                    continue;
                }
                $has_entered_maintenance_mode = $has_entered_maintenance_mode || $this->laravel->is_down_for_maintenance();
                if ($has_entered_maintenance_mode && !$event->runs_in_maintenance_mode()) {
                    continue;
                }
                if (!$event->filters_pass($this->laravel)) {
                    $this->dispatcher->dispatch(new Scheduled_Task_Skipped($event));
                    continue;
                }
                if ($event->on_one_server) {
                    $this->run_single_server_event($event);
                } else {
                    $this->run_event($event);
                }
                $this->events_ran = true;
            }
            Sleep::usleep(100000);
        }
    }
    /**
     * Determine if the schedule run should be interrupted.
     *
     * @return bool
     */
    protected function should_interrupt()
    {
        return $this->cache->get('illuminate:schedule:interrupt', false);
    }
    /**
     * Ensure the interrupt signal is cleared.
     *
     * @return void
     */
    protected function clear_interrupt_signal()
    {
        $this->cache->forget('illuminate:schedule:interrupt');
    }
}