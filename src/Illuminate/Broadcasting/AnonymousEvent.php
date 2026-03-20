<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Illuminate\Contracts\Broadcasting\Should_Broadcast;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
class Anonymous_Event implements Should_Broadcast
{
    use Dispatchable;
    use Interacts_With_Broadcasting;
    use Interacts_With_Sockets;
    /**
     * The connection the event should be broadcast on.
     */
    protected ?string $connection = null;
    /**
     * The name the event should be broadcast as.
     */
    protected ?string $name = null;
    /**
     * The payload the event should be broadcast with.
     */
    protected array $payload = [];
    /**
     * Should the broadcast include the current user.
     */
    protected bool $include_current_user = true;
    /**
     * Indicates if the event should be broadcast synchronously.
     */
    protected bool $should_broadcast_now = false;
    /**
     * Create a new anonymous broadcastable event instance.
     */
    public function __construct(protected Channel|array|string $channels)
    {
        $this->channels = Arr::wrap($channels);
    }
    /**
     * Set the connection the event should be broadcast on.
     */
    public function via(string $connection): static
    {
        $this->connection = $connection;
        return $this;
    }
    /**
     * Set the name the event should be broadcast as.
     */
    public function as(string $name): static
    {
        $this->name = $name;
        return $this;
    }
    /**
     * Set the payload the event should be broadcast with.
     */
    public function with(Arrayable|array $payload): static
    {
        $this->payload = $payload instanceof Arrayable ? $payload->to_array() : (new Collection($payload))->map(fn($p) => $p instanceof Arrayable ? $p->to_array() : $p)->all();
        return $this;
    }
    /**
     * Broadcast the event to everyone except the current user.
     */
    public function to_others(): static
    {
        $this->include_current_user = false;
        return $this;
    }
    /**
     * Broadcast the event.
     */
    public function send_now(): void
    {
        $this->should_broadcast_now = true;
        $this->send();
    }
    /**
     * Broadcast the event.
     */
    public function send(): void
    {
        $broadcast = broadcast($this)->via($this->connection);
        if (!$this->include_current_user) {
            $broadcast->to_others();
        }
    }
    /**
     * Get the name the event should broadcast as.
     */
    public function broadcast_as(): string
    {
        return $this->name ?: class_basename($this);
    }
    /**
     * Get the payload the event should broadcast with.
     *
     * @return array<string, mixed>
     */
    public function broadcast_with(): array
    {
        return $this->payload;
    }
    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|\Illuminate\Broadcasting\Channel[]|string[]|string
     */
    public function broadcast_on(): Channel|array
    {
        return $this->channels;
    }
    /**
     * Determine if the event should be broadcast synchronously.
     */
    public function should_broadcast_now(): bool
    {
        return $this->should_broadcast_now;
    }
}