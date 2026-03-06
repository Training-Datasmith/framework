<?php

declare(strict_types=1);

namespace Illuminate\Broadcasting\Broadcasters;

class LogBroadcaster extends Broadcaster
{
    /**
     * Create a new broadcaster instance.
     */
    public function __construct(
        /**
         * The logger implementation.
         */
        protected \Psr\Log\LoggerInterface $logger
    ) {
    }

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
        $channels = implode(', ', $this->formatChannels($channels));

        $payload = json_encode($payload, JSON_PRETTY_PRINT);

        $this->logger->info('Broadcasting ['.$event.'] on channels ['.$channels.'] with payload:'.PHP_EOL.$payload);
    }
}
