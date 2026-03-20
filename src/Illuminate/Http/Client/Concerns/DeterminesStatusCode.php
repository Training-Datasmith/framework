<?php

declare (strict_types=1);
namespace Illuminate\Http\Client\Concerns;

trait Determines_Status_Code
{
    /**
     * Determine if the response code was 200 "OK" response.
     */
    public function ok(): bool
    {
        return $this->status() === 200;
    }
    /**
     * Determine if the response code was 201 "Created" response.
     */
    public function created(): bool
    {
        return $this->status() === 201;
    }
    /**
     * Determine if the response code was 202 "Accepted" response.
     */
    public function accepted(): bool
    {
        return $this->status() === 202;
    }
    /**
     * Determine if the response code was the given status code and the body has no content.
     *
     * @param  int  $status
     */
    public function no_content($status = 204): bool
    {
        return $this->status() === $status && $this->body() === '';
    }
    /**
     * Determine if the response code was a 301 "Moved Permanently".
     */
    public function moved_permanently(): bool
    {
        return $this->status() === 301;
    }
    /**
     * Determine if the response code was a 302 "Found" response.
     */
    public function found(): bool
    {
        return $this->status() === 302;
    }
    /**
     * Determine if the response code was a 304 "Not Modified" response.
     */
    public function not_modified(): bool
    {
        return $this->status() === 304;
    }
    /**
     * Determine if the response was a 400 "Bad Request" response.
     */
    public function bad_request(): bool
    {
        return $this->status() === 400;
    }
    /**
     * Determine if the response was a 401 "Unauthorized" response.
     */
    public function unauthorized(): bool
    {
        return $this->status() === 401;
    }
    /**
     * Determine if the response was a 402 "Payment Required" response.
     */
    public function payment_required(): bool
    {
        return $this->status() === 402;
    }
    /**
     * Determine if the response was a 403 "Forbidden" response.
     */
    public function forbidden(): bool
    {
        return $this->status() === 403;
    }
    /**
     * Determine if the response was a 404 "Not Found" response.
     */
    public function not_found(): bool
    {
        return $this->status() === 404;
    }
    /**
     * Determine if the response was a 408 "Request Timeout" response.
     */
    public function request_timeout(): bool
    {
        return $this->status() === 408;
    }
    /**
     * Determine if the response was a 409 "Conflict" response.
     */
    public function conflict(): bool
    {
        return $this->status() === 409;
    }
    /**
     * Determine if the response was a 422 "Unprocessable Content" response.
     */
    public function unprocessable_content(): bool
    {
        return $this->status() === 422;
    }
    /**
     * Determine if the response was a 422 "Unprocessable Content" response.
     *
     * @return bool
     */
    public function unprocessable_entity()
    {
        return $this->unprocessable_content();
    }
    /**
     * Determine if the response was a 429 "Too Many Requests" response.
     */
    public function too_many_requests(): bool
    {
        return $this->status() === 429;
    }
}