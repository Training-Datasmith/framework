<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting\Broadcasters;

class Null_Broadcaster extends Broadcaster
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
    public function valid_authentication_response($request, $result): void
    {
    }
    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
    }
}