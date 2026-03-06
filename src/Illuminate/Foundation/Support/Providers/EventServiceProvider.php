<?php

namespace Illuminate\Foundation\Support\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Events\DiscoverEvents;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
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
    protected static $shouldDiscoverEvents = true;

    /**
     * The configured event discovery paths.
     *
     * @var iterable<int, string>|null
     */
    protected static $eventDiscoveryPaths;

    /**
     * Register the application's event listeners.
     */
    public function register(): void
    {
        $this->booting(function (): void {
            $events = $this->getEvents();

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
            $this->configureEmailVerification();
        });
    }

    /**
     * Boot any application services.
     */
    public function boot(): void
    {
        //
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
    public function getEvents()
    {
        if ($this->app->eventsAreCached()) {
            $cache = require $this->app->getCachedEventsPath();

            return $cache[$this::class] ?? [];
        }
        return array_merge_recursive(
            $this->discoveredEvents(),
            $this->listens()
        );
    }

    /**
     * Get the discovered events for the application.
     *
     * @return array
     */
    protected function discoveredEvents()
    {
        return $this->shouldDiscoverEvents()
            ? $this->discoverEvents()
            : [];
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return static::class === self::class && static::$shouldDiscoverEvents === true;
    }

    /**
     * Discover the events and listeners for the application.
     *
     * @return array
     */
    public function discoverEvents()
    {
        return (new LazyCollection($this->discoverEventsWithin()))
            ->flatMap(fn($directory) => glob($directory, GLOB_ONLYDIR))
            ->reject(fn($directory) => ! is_dir($directory))
            ->pipe(fn ($directories): array => DiscoverEvents::within(
                $directories->all(),
                $this->eventDiscoveryBasePath(),
            ));
    }

    /**
     * Get the listener directories that should be used to discover events.
     *
     * @return iterable<int, string>
     */
    protected function discoverEventsWithin()
    {
        return static::$eventDiscoveryPaths ?: [
            $this->app->path('Listeners'),
        ];
    }

    /**
     * Add the given event discovery paths to the application's event discovery paths.
     *
     * @param  string|iterable<int, string>  $paths
     */
    public static function addEventDiscoveryPaths(iterable|string $paths): void
    {
        static::$eventDiscoveryPaths = (new LazyCollection(static::$eventDiscoveryPaths))
            ->merge(is_string($paths) ? [$paths] : $paths)
            ->unique()
            ->values();
    }

    /**
     * Set the globally configured event discovery paths.
     *
     * @param  iterable<int, string>  $paths
     */
    public static function setEventDiscoveryPaths(iterable $paths): void
    {
        static::$eventDiscoveryPaths = $paths;
    }

    /**
     * Get the base path to be used during event discovery.
     */
    protected function eventDiscoveryBasePath(): string
    {
        return base_path();
    }

    /**
     * Disable event discovery for the application.
     */
    public static function disableEventDiscovery(): void
    {
        static::$shouldDiscoverEvents = false;
    }

    /**
     * Configure the proper event listeners for email verification.
     *
     * @return void
     */
    protected function configureEmailVerification()
    {
        if (! isset($this->listen[Registered::class]) ||
            ! in_array(SendEmailVerificationNotification::class, Arr::wrap($this->listen[Registered::class]))) {
            Event::listen(Registered::class, SendEmailVerificationNotification::class);
        }
    }
}
