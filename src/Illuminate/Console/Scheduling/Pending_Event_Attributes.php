<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

/**
 * @mixin \Illuminate\Console\Scheduling\Schedule
 */
class Pending_Event_Attributes
{
    use Manages_Attributes;
    use Manages_Frequencies;
    /**
     * The recorded macro calls to replay on each event.
     *
     * @var array<int, array{string, array}>
     */
    protected array $macros = [];
    /**
     * Create a new pending event attributes instance.
     */
    public function __construct(protected Schedule $schedule)
    {
    }
    /**
     * Do not allow the event to overlap each other.
     *
     * The expiration time of the underlying cache lock may be specified in minutes.
     *
     * @param  int  $expiresAt
     * @return $this
     */
    public function without_overlapping($expires_at = 1440): static
    {
        $this->without_overlapping = true;
        $this->expires_at = $expires_at;
        return $this;
    }
    /**
     * Merge the current attributes into the given event.
     */
    public function merge_attributes(Event $event): void
    {
        $event->expression = $this->expression;
        $event->repeat_seconds = $this->repeat_seconds;
        if ($this->description !== null) {
            $event->name($this->description);
        }
        if ($this->timezone !== null) {
            $event->timezone($this->timezone);
        }
        if ($this->user !== null) {
            $event->user = $this->user;
        }
        if (!empty($this->environments)) {
            $event->environments($this->environments);
        }
        if ($this->even_in_maintenance_mode) {
            $event->even_in_maintenance_mode();
        }
        if ($this->without_overlapping) {
            $event->without_overlapping($this->expires_at);
        }
        if ($this->on_one_server) {
            $event->on_one_server();
        }
        if ($this->run_in_background) {
            $event->run_in_background();
        }
        foreach ($this->filters as $filter) {
            $event->when($filter);
        }
        foreach ($this->rejects as $reject) {
            $event->skip($reject);
        }
        foreach ($this->macros as [$method, $parameters]) {
            $event->{$method}(...$parameters);
        }
    }
    /**
     * Proxy missing methods onto the underlying schedule.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (Event::has_macro($method)) {
            $this->macros[] = [$method, $parameters];
            return $this;
        }
        return $this->schedule->{$method}(...$parameters);
    }
}