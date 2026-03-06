<?php

declare(strict_types=1);

namespace Illuminate\Auth\Access;

use Illuminate\Contracts\Support\Arrayable;
use Stringable;

class Response implements Arrayable, Stringable
{
    /**
     * The HTTP response status code.
     *
     * @var int|null
     */
    protected $status;

    /**
     * Create a new response.
     *
     * @param  bool  $allowed
     * @param  string|null  $message
     * @param  mixed  $code
     */
    public function __construct(
        /**
         * Indicates whether the response was allowed.
         */
        protected $allowed,
        /**
         * The response message.
         */
        protected $message = '',
        /**
         * The response code.
         */
        protected $code = null
    ) {
    }

    /**
     * Create a new "allow" Response.
     *
     * @param  string|null  $message
     * @param  mixed  $code
     */
    public static function allow($message = null, $code = null): static
    {
        return new static(true, $message, $code);
    }

    /**
     * Create a new "deny" Response.
     *
     * @param  string|null  $message
     * @param  mixed  $code
     */
    public static function deny($message = null, $code = null): static
    {
        return new static(false, $message, $code);
    }

    /**
     * Create a new "deny" Response with a HTTP status code.
     *
     * @param  int  $status
     * @param  string|null  $message
     * @param  mixed  $code
     */
    public static function denyWithStatus($status, $message = null, $code = null): static
    {
        return static::deny($message, $code)->withStatus($status);
    }

    /**
     * Create a new "deny" Response with a 404 HTTP status code.
     *
     * @param  string|null  $message
     * @param  mixed  $code
     * @return \Illuminate\Auth\Access\Response
     */
    public static function denyAsNotFound($message = null, $code = null)
    {
        return static::denyWithStatus(404, $message, $code);
    }

    /**
     * Determine if the response was allowed.
     *
     * @return bool
     */
    public function allowed()
    {
        return $this->allowed;
    }

    /**
     * Determine if the response was denied.
     */
    public function denied(): bool
    {
        return ! $this->allowed();
    }

    /**
     * Get the response message.
     *
     * @return string|null
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * Get the response code / reason.
     *
     * @return mixed
     */
    public function code()
    {
        return $this->code;
    }

    /**
     * Throw authorization exception if response was denied.
     *
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize(): static
    {
        if ($this->denied()) {
            throw (new AuthorizationException($this->message(), $this->code()))
                ->setResponse($this)
                ->withStatus($this->status);
        }

        return $this;
    }

    /**
     * Set the HTTP response status code.
     *
     * @param  null|int  $status
     * @return $this
     */
    public function withStatus($status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Set the HTTP response status code to 404.
     *
     * @return $this
     */
    public function asNotFound(): static
    {
        return $this->withStatus(404);
    }

    /**
     * Get the HTTP status code.
     *
     * @return int|null
     */
    public function status()
    {
        return $this->status;
    }

    /**
     * Convert the response to an array.
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed(),
            'message' => $this->message(),
            'code' => $this->code(),
        ];
    }

    /**
     * Get the string representation of the message.
     */
    public function __toString(): string
    {
        return (string) $this->message();
    }
}
