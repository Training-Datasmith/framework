<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Closure;
use Cron\Cron_Expression;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionFunction;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Terminal;
#[As_Command(name: 'schedule:list')]
class Schedule_List_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'schedule:list
        {--timezone= : The timezone that times should be displayed in}
        {--next : Sort the listed tasks by their next due date}
        {--json : Output the scheduled tasks as JSON}
    ';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all scheduled tasks';
    /**
     * The terminal width resolver callback.
     *
     * @var \Closure|null
     */
    protected static $terminal_width_resolver;
    /**
     * Execute the console command.
     *
     *
     * @throws \Exception
     */
    public function handle(Schedule $schedule): void
    {
        $events = new Collection($schedule->events());
        if ($events->is_empty()) {
            if ($this->option('json')) {
                $this->output->writeln('[]');
            } else {
                $this->components->info('No scheduled tasks have been defined.');
            }
            return;
        }
        $timezone = new DateTimeZone($this->option('timezone') ?? config('app.timezone'));
        $events = $this->sort_events($events, $timezone);
        $this->display($events, $timezone);
    }
    /**
     * Render the scheduled tasks information as JSON.
     *
     * @return void
     */
    protected function display_json(Collection $events, DateTimeZone $timezone)
    {
        $this->output->writeln($events->map(function ($event) use ($timezone): array {
            $next_due_date = $this->get_next_due_date_for_event($event, $timezone);
            $command = $event->command ?? '';
            if (!$this->output->is_verbose()) {
                $command = $event->normalize_command($command);
            }
            if ($event instanceof Callback_Event) {
                $command = $event->get_summary_for_display();
                if (in_array($command, ['Closure', 'Callback'])) {
                    $command = 'Closure at: ' . $this->get_closure_location($event);
                }
            }
            return ['expression' => $event->expression, 'command' => $command, 'description' => $event->description ?? null, 'next_due_date' => $next_due_date->format('Y-m-d H:i:s P'), 'next_due_date_human' => $next_due_date->diff_for_humans(), 'timezone' => $timezone->get_name(), 'has_mutex' => $event->mutex->exists($event), 'repeat_seconds' => $event->is_repeatable() ? $event->repeat_seconds : null, 'environments' => $event->environments];
        })->values()->to_json());
    }
    /**
     * Render the scheduled tasks information formatted for the CLI.
     *
     * @return void
     */
    protected function display_for_cli(Collection $events, DateTimeZone $timezone)
    {
        $terminal_width = self::get_terminal_width();
        $expression_spacing = $this->get_cron_expression_spacing($events);
        $repeat_expression_spacing = $this->get_repeat_expression_spacing($events);
        $events = $events->map(fn($event): array => $this->list_event($event, $terminal_width, $expression_spacing, $repeat_expression_spacing, $timezone));
        $this->line($events->flatten()->filter()->prepend('')->push('')->to_array());
    }
    /**
     * Get the spacing to be used on each event row.
     *
     * @return array<int, int>
     */
    private function get_cron_expression_spacing(\Illuminate\Support\Collection $events)
    {
        $rows = $events->map(fn($event): array => array_map(mb_strlen(...), preg_split("/\\s+/", (string) $event->expression)));
        return (new Collection($rows[0] ?? []))->keys()->map(fn($key) => $rows->max($key))->all();
    }
    /**
     * Get the spacing to be used on each event row.
     *
     * @return int
     */
    private function get_repeat_expression_spacing(\Illuminate\Support\Collection $events)
    {
        return $events->map(fn($event): int => mb_strlen($this->get_repeat_expression($event)))->max();
    }
    /**
     * List the given even in the console.
     *
     * @param  \Illuminate\Console\Scheduling\Event  $event
     * @param  int  $terminalWidth
     * @param  array  $expressionSpacing
     * @param  int  $repeatExpressionSpacing
     */
    private function list_event($event, $terminal_width, $expression_spacing, $repeat_expression_spacing, \DateTimeZone $timezone): array
    {
        $expression = $this->format_cron_expression($event->expression, $expression_spacing);
        $repeat_expression = str_pad($this->get_repeat_expression($event), $repeat_expression_spacing);
        $command = $event->command ?? '';
        $description = $event->description ?? '';
        if (!$this->output->is_verbose()) {
            $command = $event->normalize_command($command);
        }
        if ($event instanceof Callback_Event) {
            $command = $event->get_summary_for_display();
            if (in_array($command, ['Closure', 'Callback'])) {
                $command = 'Closure at: ' . $this->get_closure_location($event);
            }
        }
        $command = mb_strlen($command) > 1 ? "{$command} " : '';
        $next_due_date_label = 'Next Due:';
        $next_due_date = $this->get_next_due_date_for_event($event, $timezone);
        $next_due_date = $this->output->is_verbose() ? $next_due_date->format('Y-m-d H:i:s P') : $next_due_date->diff_for_humans();
        $has_mutex = $event->mutex->exists($event) ? 'Has Mutex › ' : '';
        $dots = str_repeat('.', max($terminal_width - mb_strwidth($expression . $repeat_expression . $command . $next_due_date_label . $next_due_date . $has_mutex) - 8, 0));
        // Highlight the parameters...
        $command = preg_replace("#(php artisan [\\w\\-:]+) (.+)#", '$1 <fg=yellow;options=bold>$2</>', $command);
        return [sprintf('  <fg=yellow>%s</> <fg=#6C7280>%s</> %s<fg=#6C7280>%s %s%s %s</>', $expression, $repeat_expression, $command, $dots, $has_mutex, $next_due_date_label, $next_due_date), $this->output->is_verbose() && mb_strlen($description) > 1 ? sprintf('  <fg=#6C7280>%s%s %s</>', str_repeat(' ', mb_strlen($expression) + 2), '⇁', $description) : ''];
    }
    /**
     * Get the repeat expression for an event.
     *
     * @param  \Illuminate\Console\Scheduling\Event  $event
     */
    private function get_repeat_expression($event): string
    {
        return $event->is_repeatable() ? "{$event->repeat_seconds}s " : '';
    }
    /**
     * Sort the events by due date if option set.
     *
     * @return \Illuminate\Support\Collection
     */
    private function sort_events(\Illuminate\Support\Collection $events, DateTimeZone $timezone)
    {
        return $this->option('next') ? $events->sort_by(fn($event) => $this->get_next_due_date_for_event($event, $timezone)) : $events;
    }
    /**
     * Render the scheduled tasks information.
     *
     * @return void
     */
    protected function display(Collection $events, DateTimeZone $timezone)
    {
        $this->option('json') ? $this->display_json($events, $timezone) : $this->display_for_cli($events, $timezone);
    }
    /**
     * Get the next due date for an event.
     *
     * @param  \Illuminate\Console\Scheduling\Event  $event
     * @return \Illuminate\Support\Carbon
     */
    private function get_next_due_date_for_event($event, DateTimeZone $timezone)
    {
        $next_due_date = Carbon::instance((new Cron_Expression($event->expression))->get_next_run_date(Carbon::now()->set_timezone($event->timezone))->set_timezone($timezone));
        if (!$event->is_repeatable()) {
            return $next_due_date;
        }
        $previous_due_date = Carbon::instance((new Cron_Expression($event->expression))->get_previous_run_date(Carbon::now()->set_timezone($event->timezone), allowCurrentDate: true)->set_timezone($timezone));
        $now = Carbon::now()->set_timezone($event->timezone);
        if (!$now->copy()->start_of_minute()->eq($previous_due_date)) {
            return $next_due_date;
        }
        return $now->end_of_second()->ceil_seconds($event->repeat_seconds);
    }
    /**
     * Format the cron expression based on the spacing provided.
     *
     * @param  string  $expression
     * @param  array<int, int>  $spacing
     */
    private function format_cron_expression($expression, $spacing): string
    {
        $expressions = preg_split("/\\s+/", $expression);
        return (new Collection($spacing))->map(fn($length, $index): string => str_pad($expressions[$index], $length))->implode(' ');
    }
    /**
     * Get the file and line number for the event closure.
     */
    private function get_closure_location(Callback_Event $event): string
    {
        $callback = (new ReflectionClass($event))->get_property('callback')->get_value($event);
        if ($callback instanceof Closure) {
            $function = new ReflectionFunction($callback);
            return sprintf('%s:%s', str_replace($this->laravel->base_path() . DIRECTORY_SEPARATOR, '', $function->get_file_name() ?: ''), $function->get_start_line());
        }
        if (is_string($callback)) {
            return $callback;
        }
        if (is_array($callback)) {
            $class_name = is_string($callback[0]) ? $callback[0] : $callback[0]::class;
            return sprintf('%s::%s', $class_name, $callback[1]);
        }
        return sprintf('%s::__invoke', $callback::class);
    }
    /**
     * Get the terminal width.
     *
     * @return int
     */
    public static function get_terminal_width()
    {
        return is_null(static::$terminal_width_resolver) ? (new Terminal())->get_width() : call_user_func(static::$terminal_width_resolver);
    }
    /**
     * Set a callback that should be used when resolving the terminal width.
     *
     * @param  \Closure|null  $resolver
     */
    public static function resolve_terminal_width_using($resolver): void
    {
        static::$terminal_width_resolver = $resolver;
    }
}