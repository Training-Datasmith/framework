<?php

namespace Illuminate\Http\Client;

use GuzzleHttp\Psr7\Message;

class RequestException extends HttpClientException
{
    /**
     * The response instance.
     *
     * @var \Illuminate\Http\Client\Response
     */
    public $response;

    /**
     * The global truncation length for the exception message.
     *
     * @var int|false
     */
    public static $truncateAt = 120;

    /**
     * Whether the response has been summarized in the message.
     *
     * @var bool
     */
    public $hasBeenSummarized = false;

    /**
     * Create a new exception instance.
     *
     * @param  int|false|null  $truncateExceptionsAt
     */
    public function __construct(Response $response, /**
     * The current truncation length for the exception message.
     */
    public $truncateExceptionsAt = null)
    {
        parent::__construct($this->prepareMessage($response), $response->status());

        $this->response = $response;
    }

    /**
     * Enable truncation of request exception messages.
     */
    public static function truncate(): void
    {
        static::$truncateAt = 120;
    }

    /**
     * Set the truncation length for request exception messages.
     */
    public static function truncateAt(int $length): void
    {
        static::$truncateAt = $length;
    }

    /**
     * Disable truncation of request exception messages.
     */
    public static function dontTruncate(): void
    {
        static::$truncateAt = false;
    }

    /**
     * Prepare the exception message.
     */
    public function report(): bool
    {
        if (! $this->hasBeenSummarized) {
            $this->message = $this->prepareMessage($this->response);

            $this->hasBeenSummarized = true;
        }

        return false;
    }

    /**
     * Prepare the exception message.
     */
    protected function prepareMessage(Response $response): string
    {
        $message = "HTTP request returned status code {$response->status()}";

        $truncateExceptionsAt = $this->truncateExceptionsAt ?? static::$truncateAt;

        $psrResponse = $response->toPsrResponse();

        $summary = null;

        if (is_int($truncateExceptionsAt)) {
            $summary = Message::bodySummary($psrResponse, $truncateExceptionsAt);
        } elseif (($body = $psrResponse->getBody())->isSeekable() && $body->isReadable()) {
            $summary = Message::toString($psrResponse);
        }

        return is_null($summary) ? $message : $message.":\n{$summary}\n";
    }
}
