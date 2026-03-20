<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

use Guzzle_Http\Psr7\Message;
class Request_Exception extends Http_Client_Exception
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
    public static $truncate_at = 120;
    /**
     * Whether the response has been summarized in the message.
     *
     * @var bool
     */
    public $has_been_summarized = false;
    /**
     * Create a new exception instance.
     *
     * @param  int|false|null  $truncateExceptionsAt
     */
    public function __construct(
        Response $response,
        /**
         * The current truncation length for the exception message.
         */
        public $truncate_exceptions_at = null
    )
    {
        parent::__construct($this->prepare_message($response), $response->status());
        $this->response = $response;
    }
    /**
     * Enable truncation of request exception messages.
     */
    public static function truncate(): void
    {
        static::$truncate_at = 120;
    }
    /**
     * Set the truncation length for request exception messages.
     */
    public static function truncate_at(int $length): void
    {
        static::$truncate_at = $length;
    }
    /**
     * Disable truncation of request exception messages.
     */
    public static function dont_truncate(): void
    {
        static::$truncate_at = false;
    }
    /**
     * Prepare the exception message.
     */
    public function report(): bool
    {
        if (!$this->has_been_summarized) {
            $this->message = $this->prepare_message($this->response);
            $this->has_been_summarized = true;
        }
        return false;
    }
    /**
     * Prepare the exception message.
     */
    protected function prepare_message(Response $response): string
    {
        $message = "HTTP request returned status code {$response->status()}";
        $truncate_exceptions_at = $this->truncate_exceptions_at ?? static::$truncate_at;
        $psr_response = $response->to_psr_response();
        $summary = null;
        if (is_int($truncate_exceptions_at)) {
            $summary = Message::body_summary($psr_response, $truncate_exceptions_at);
        } elseif (($body = $psr_response->get_body())->is_seekable() && $body->is_readable()) {
            $summary = Message::to_string($psr_response);
        }
        return is_null($summary) ? $message : $message . ":\n{$summary}\n";
    }
}