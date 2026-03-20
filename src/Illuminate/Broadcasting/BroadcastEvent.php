<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Queue\Should_Queue;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionProperty;
use Throwable;
class Broadcast_Event implements Should_Queue
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
    public $max_exceptions;
    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $delete_when_missing_models = true;
    /**
     * Create a new job handler instance.
     *
     * @param  mixed  $event
     */
    public function __construct(
        /**
         * The event instance.
         */
        public $event
    )
    {
        $this->tries = property_exists($this->event, 'tries') ? $this->event->tries : null;
        $this->timeout = property_exists($this->event, 'timeout') ? $this->event->timeout : null;
        $this->backoff = property_exists($this->event, 'backoff') ? $this->event->backoff : null;
        $this->after_commit = property_exists($this->event, 'afterCommit') ? $this->event->after_commit : null;
        $this->max_exceptions = property_exists($this->event, 'maxExceptions') ? $this->event->max_exceptions : null;
    }
    /**
     * Handle the queued job.
     */
    public function handle(Broadcasting_Factory $manager): void
    {
        $name = method_exists($this->event, 'broadcastAs') ? $this->event->broadcast_as() : $this->event::class;
        $channels = Arr::wrap($this->event->broadcast_on());
        if (empty($channels)) {
            return;
        }
        $connections = method_exists($this->event, 'broadcastConnections') ? $this->event->broadcast_connections() : [null];
        $payload = $this->get_payload_from_event($this->event);
        foreach ($connections as $connection) {
            $manager->connection($connection)->broadcast($this->get_connection_channels($channels, $connection), $name, $this->get_connection_payload($payload, $connection));
        }
    }
    /**
     * Get the payload for the given event.
     *
     * @param  mixed  $event
     */
    protected function get_payload_from_event($event): array
    {
        if (method_exists($event, 'broadcastWith') && !is_null($payload = $event->broadcast_with())) {
            return array_merge($payload, ['socket' => data_get($event, 'socket')]);
        }
        $payload = [];
        foreach ((new ReflectionClass($event))->get_properties(ReflectionProperty::IS_PUBLIC) as $property) {
            $payload[$property->get_name()] = $this->format_property($property->get_value($event));
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
    protected function format_property($value)
    {
        if ($value instanceof Arrayable) {
            return $value->to_array();
        }
        return $value;
    }
    /**
     * Get the channels for the given connection.
     *
     * @param  string|null  $connection
     */
    protected function get_connection_channels(array $channels, $connection): array
    {
        return is_array($channels[$connection ?? ''] ?? null) ? $channels[$connection ?? ''] : $channels;
    }
    /**
     * Get the payload for the given connection.
     *
     * @param  string|null  $connection
     */
    protected function get_connection_payload(array $payload, $connection): array
    {
        $connection_payload = is_array($payload[$connection ?? ''] ?? null) ? $payload[$connection ?? ''] : $payload;
        if (isset($payload['socket'])) {
            $connection_payload['socket'] = $payload['socket'];
        }
        return $connection_payload;
    }
    /**
     * Get the middleware for the underlying event.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        if (!method_exists($this->event, 'middleware')) {
            return [];
        }
        return $this->event->middleware();
    }
    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $e = null): void
    {
        if (!method_exists($this->event, 'failed')) {
            return;
        }
        $this->event->failed($e);
    }
    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function display_name(): string|false
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