<?php

declare(strict_types=1);

namespace Illuminate\Broadcasting\Broadcasters;

class NullBroadcaster extends Broadcaster
{
    /**
     * {@inheritdoc}
     */
    public function auth($request): void
    {

    }

    /**
     * {@inheritdoc}
     */
    public function validAuthenticationResponse($request, $result): void
    {

    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {

    }
}
