<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\Should_Broadcast;
use Illuminate\Contracts\Queue\Should_Queue;
use Illuminate\Support\Collection;
use ReflectionFunction;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'event:list')]
class Event_List_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:list
                            {--event= : Filter the events by name}
                            {--json : Output the events and listeners as JSON}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List the application's events and listeners";
    /**
     * The events dispatcher resolver callback.
     *
     * @var \Closure|null
     */
    protected static $events_resolver;
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $events = $this->get_events()->sort_keys();
        if ($events->is_empty()) {
            if ($this->option('json')) {
                $this->output->writeln('[]');
            } else {
                $this->components->info("Your application doesn't have any events matching the given criteria.");
            }
            return;
        }
        if ($this->option('json')) {
            $this->display_json($events);
        } else {
            $this->display_for_cli($events);
        }
    }
    /**
     * Display events and their listeners in JSON.
     *
     * @return void
     */
    protected function display_json(Collection $events)
    {
        $data = $events->map(fn($listeners, $event): array => ['event' => strip_tags($this->append_event_interfaces($event)), 'listeners' => (new Collection($listeners))->map(fn($listener): string => strip_tags((string) $listener))->values()->all()])->values();
        $this->output->writeln($data->to_json());
    }
    /**
     * Display the events and their listeners for the CLI.
     *
     * @return void
     */
    protected function display_for_cli(Collection $events)
    {
        $this->new_line();
        $events->each(function ($listeners, $event): void {
            $this->components->two_column_detail($this->append_event_interfaces($event));
            $this->components->bullet_list($listeners);
        });
        $this->new_line();
    }
    /**
     * Get all of the events and listeners configured for the application.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function get_events()
    {
        $events = new Collection($this->get_listeners_on_dispatcher());
        if ($this->filtering_by_event()) {
            return $this->filter_events($events);
        }
        return $events;
    }
    /**
     * Get the event / listeners from the dispatcher object.
     */
    protected function get_listeners_on_dispatcher(): array
    {
        $events = [];
        foreach ($this->get_raw_listeners() as $event => $raw_listeners) {
            foreach ($raw_listeners as $raw_listener) {
                if (is_string($raw_listener)) {
                    $events[$event][] = $this->append_listener_interfaces($raw_listener);
                } elseif ($raw_listener instanceof Closure) {
                    $events[$event][] = $this->stringify_closure($raw_listener);
                } elseif (is_array($raw_listener) && count($raw_listener) === 2) {
                    if (is_object($raw_listener[0])) {
                        $raw_listener[0] = $raw_listener[0]::class;
                    }
                    $events[$event][] = $this->append_listener_interfaces(implode('@', $raw_listener));
                }
            }
        }
        return $events;
    }
    /**
     * Add the event implemented interfaces to the output.
     *
     * @param  string  $event
     * @return string
     */
    protected function append_event_interfaces($event)
    {
        if (!class_exists($event)) {
            return $event;
        }
        $interfaces = class_implements($event);
        if (in_array(Should_Broadcast::class, $interfaces)) {
            $event .= ' <fg=bright-blue>(ShouldBroadcast)</>';
        }
        return $event;
    }
    /**
     * Add the listener implemented interfaces to the output.
     *
     * @param  string  $listener
     */
    protected function append_listener_interfaces($listener): string
    {
        $listener = explode('@', $listener);
        $interfaces = class_implements($listener[0]);
        $listener = implode('@', $listener);
        if (in_array(Should_Queue::class, $interfaces)) {
            $listener .= ' <fg=bright-blue>(ShouldQueue)</>';
        }
        return $listener;
    }
    /**
     * Get a displayable string representation of a Closure listener.
     */
    protected function stringify_closure(Closure $raw_listener): string
    {
        $reflection = new ReflectionFunction($raw_listener);
        $path = str_replace([base_path(), DIRECTORY_SEPARATOR], ['', '/'], $reflection->get_file_name() ?: '');
        return 'Closure at: ' . $path . ':' . $reflection->get_start_line();
    }
    /**
     * Filter the given events using the provided event name filter.
     *
     * @param  \Illuminate\Support\Collection  $events
     * @return \Illuminate\Support\Collection
     */
    protected function filter_events($events)
    {
        if (!$event_name = $this->option('event')) {
            return $events;
        }
        return $events->filter(fn($listeners, $event): bool => str_contains((string) $event, $event_name));
    }
    /**
     * Determine whether the user is filtering by an event name.
     */
    protected function filtering_by_event(): bool
    {
        return !empty($this->option('event'));
    }
    /**
     * Gets the raw version of event listeners from the event dispatcher.
     *
     * @return array
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function get_raw_listeners()
    {
        return $this->get_events_dispatcher()->get_raw_listeners();
    }
    /**
     * Get the event dispatcher.
     *
     * @return \Illuminate\Events\Dispatcher
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function get_events_dispatcher()
    {
        return is_null(self::$events_resolver) ? $this->get_laravel()->make('events') : call_user_func(self::$events_resolver);
    }
    /**
     * Set a callback that should be used when resolving the events dispatcher.
     *
     * @param  \Closure|null  $resolver
     */
    public static function resolve_events_using($resolver): void
    {
        static::$events_resolver = $resolver;
    }
}