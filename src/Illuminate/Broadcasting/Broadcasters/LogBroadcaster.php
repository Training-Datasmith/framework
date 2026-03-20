<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting\Broadcasters;

class Log_Broadcaster extends Broadcaster
{
    /**
     * Create a new broadcaster instance.
     */
    public function __construct(
        /**
         * The logger implementation.
         */
        protected \Psr\Log\Logger_Interface $logger
    )
    {
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
    public function valid_authentication_response($request, $result): void
    {
    }
    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $channels = implode(', ', $this->format_channels($channels));
        $payload = json_encode($payload, JSON_PRETTY_PRINT);
        $this->logger->info('Broadcasting [' . $event . '] on channels [' . $channels . '] with payload:' . PHP_EOL . $payload);
    }
}