<?php

declare(strict_types=1);

namespace Illuminate\Broadcasting;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

class BroadcastEvent implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * The number of seconds to wait before retrying the job when encountering an uncaught exception.
     *
     * @var int
     */
    public $backoff;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job handler instance.
     *
     * @param  mixed  $event
     */
    public function __construct(/**
     * The event instance.
     */
        public $event
    ) {
        $this->tries = property_exists($this->event, 'tries') ? $this->event->tries : null;
        $this->timeout = property_exists($this->event, 'timeout') ? $this->event->timeout : null;
        $this->backoff = property_exists($this->event, 'backoff') ? $this->event->backoff : null;
        $this->afterCommit = property_exists($this->event, 'afterCommit') ? $this->event->afterCommit : null;
        $this->maxExceptions = property_exists($this->event, 'maxExceptions') ? $this->event->maxExceptions : null;
    }

    /**
     * Handle the queued job.
     */
    public function handle(BroadcastingFactory $manager): void
    {
        $name = method_exists($this->event, 'broadcastAs')
            ? $this->event->broadcastAs()
            : $this->event::class;

        $channels = Arr::wrap($this->event->broadcastOn());

        if (empty($channels)) {
            return;
        }

        $connections = method_exists($this->event, 'broadcastConnections')
            ? $this->event->broadcastConnections()
            : [null];

        $payload = $this->getPayloadFromEvent($this->event);

        foreach ($connections as $connection) {
            $manager->connection($connection)->broadcast(
                $this->getConnectionChannels($channels, $connection),
                $name,
                $this->getConnectionPayload($payload, $connection)
            );
        }
    }

    /**
     * Get the payload for the given event.
     *
     * @param  mixed  $event
     */
    protected function getPayloadFromEvent($event): array
    {
        if (method_exists($event, 'broadcastWith') &&
            ! is_null($payload = $event->broadcastWith())) {
            return array_merge($payload, ['socket' => data_get($event, 'socket')]);
        }

        $payload = [];

        foreach ((new ReflectionClass($event))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $payload[$property->getName()] = $this->formatProperty($property->getValue($event));
        }

        unset($payload['broadcastQueue']);

        return $payload;
    }

    /**
     * Format the given value for a property.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function formatProperty($value)
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Get the channels for the given connection.
     *
     * @param  string|null  $connection
     */
    protected function getConnectionChannels(array $channels, $connection): array
    {
        return is_array($channels[$connection ?? ''] ?? null)
            ? $channels[$connection ?? '']
            : $channels;
    }

    /**
     * Get the payload for the given connection.
     *
     * @param  string|null  $connection
     */
    protected function getConnectionPayload(array $payload, $connection): array
    {
        $connectionPayload = is_array($payload[$connection ?? ''] ?? null)
            ? $payload[$connection ?? '']
            : $payload;

        if (isset($payload['socket'])) {
            $connectionPayload['socket'] = $payload['socket'];
        }

        return $connectionPayload;
    }

    /**
     * Get the middleware for the underlying event.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        if (! method_exists($this->event, 'middleware')) {
            return [];
        }

        return $this->event->middleware();
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $e = null): void
    {
        if (! method_exists($this->event, 'failed')) {
            return;
        }

        $this->event->failed($e);
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName(): string|false
    {
        return $this->event::class;
    }

    /**
     * Prepare the instance for cloning.
     */
    public function __clone()
    {
        $this->event = clone $this->event;
    }
}
