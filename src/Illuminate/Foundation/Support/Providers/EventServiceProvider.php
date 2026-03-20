<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Support\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\Send_Email_Verification_Notification;
use Illuminate\Foundation\Events\Discover_Events;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Lazy_Collection;
use Illuminate\Support\Service_Provider;
class Event_Service_Provider extends Service_Provider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [];
    /**
     * The subscribers to register.
     *
     * @var array
     */
    protected $subscribe = [];
    /**
     * The model observers to register.
     *
     * @var array<string, string|object|array<int, string|object>>
     */
    protected $observers = [];
    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $should_discover_events = true;
    /**
     * The configured event discovery paths.
     *
     * @var iterable<int, string>|null
     */
    protected static $event_discovery_paths;
    /**
     * Register the application's event listeners.
     */
    public function register(): void
    {
        $this->booting(function (): void {
            $events = $this->get_events();
            foreach ($events as $event => $listeners) {
                foreach (array_unique($listeners, SORT_REGULAR) as $listener) {
                    Event::listen($event, $listener);
                }
            }
            foreach ($this->subscribe as $subscriber) {
                Event::subscribe($subscriber);
            }
            foreach ($this->observers as $model => $observers) {
                $model::observe($observers);
            }
        });
        $this->booted(function (): void {
            $this->configure_email_verification();
        });
    }
    /**
     * Boot any application services.
     */
    public function boot(): void
    {
    }
    /**
     * Get the events and handlers.
     *
     * @return array
     */
    public function listens()
    {
        return $this->listen;
    }
    /**
     * Get the discovered events and listeners for the application.
     *
     * @return array
     */
    public function get_events()
    {
        if ($this->app->events_are_cached()) {
            $cache = require $this->app->get_cached_events_path();
            return $cache[static::class] ?? [];
        }
        return array_merge_recursive($this->discovered_events(), $this->listens());
    }
    /**
     * Get the discovered events for the application.
     *
     * @return array
     */
    protected function discovered_events()
    {
        return $this->should_discover_events() ? $this->discover_events() : [];
    }
    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function should_discover_events(): bool
    {
        return static::class === self::class && static::$should_discover_events === true;
    }
    /**
     * Discover the events and listeners for the application.
     *
     * @return array
     */
    public function discover_events()
    {
        return (new Lazy_Collection($this->discover_events_within()))->flat_map(fn($directory) => glob($directory, GLOB_ONLYDIR))->reject(fn($directory): bool => !is_dir($directory))->pipe(fn($directories): array => Discover_Events::within($directories->all(), $this->event_discovery_base_path()));
    }
    /**
     * Get the listener directories that should be used to discover events.
     *
     * @return iterable<int, string>
     */
    protected function discover_events_within()
    {
        return static::$event_discovery_paths ?: [$this->app->path('Listeners')];
    }
    /**
     * Add the given event discovery paths to the application's event discovery paths.
     *
     * @param  string|iterable<int, string>  $paths
     */
    public static function add_event_discovery_paths(iterable|string $paths): void
    {
        static::$event_discovery_paths = (new Lazy_Collection(static::$event_discovery_paths))->merge(is_string($paths) ? [$paths] : $paths)->unique()->values();
    }
    /**
     * Set the globally configured event discovery paths.
     *
     * @param  iterable<int, string>  $paths
     */
    public static function set_event_discovery_paths(iterable $paths): void
    {
        static::$event_discovery_paths = $paths;
    }
    /**
     * Get the base path to be used during event discovery.
     */
    protected function event_discovery_base_path(): string
    {
        return base_path();
    }
    /**
     * Disable event discovery for the application.
     */
    public static function disable_event_discovery(): void
    {
        static::$should_discover_events = false;
    }
    /**
     * Configure the proper event listeners for email verification.
     *
     * @return void
     */
    protected function configure_email_verification()
    {
        if (!isset($this->listen[Registered::class]) || !in_array(Send_Email_Verification_Notification::class, Arr::wrap($this->listen[Registered::class]))) {
            Event::listen(Registered::class, Send_Email_Verification_Notification::class);
        }
    }
}