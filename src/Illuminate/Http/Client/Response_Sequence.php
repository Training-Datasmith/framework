<?php

declare (strict_types=1);
namespace Illuminate\Http\Client;

use Closure;
use Illuminate\Support\Traits\Macroable;
use OutOfBoundsException;
class Response_Sequence
{
    use Macroable;
    /**
     * Indicates that invoking this sequence when it is empty should throw an exception.
     *
     * @var bool
     */
    protected $fail_when_empty = true;
    /**
     * The response that should be returned when the sequence is empty.
     *
     * @var \GuzzleHttp\Promise\PromiseInterface
     */
    protected $empty_response;
    /**
     * Create a new response sequence.
     */
    public function __construct(
        /**
         * The responses in the sequence.
         */
        protected array $responses
    )
    {
    }
    /**
     * Push a response to the sequence.
     *
     * @param  string|array|null  $body
     * @return $this
     */
    public function push($body = null, int $status = 200, array $headers = []): static
    {
        return $this->push_response(Factory::response($body, $status, $headers));
    }
    /**
     * Push a response with the given status code to the sequence.
     *
     * @return $this
     */
    public function push_status(int $status, array $headers = []): static
    {
        return $this->push_response(Factory::response('', $status, $headers));
    }
    /**
     * Push a response with the contents of a file as the body to the sequence.
     *
     * @return $this
     */
    public function push_file(string $file_path, int $status = 200, array $headers = []): static
    {
        $string = file_get_contents($file_path);
        return $this->push_response(Factory::response($string, $status, $headers));
    }
    /**
     * Push a connection exception to the sequence.
     *
     * @param  string|null  $message
     * @return $this
     */
    public function push_failed_connection($message = null): static
    {
        return $this->push_response(Factory::failed_connection($message));
    }
    /**
     * Push a response to the sequence.
     *
     * @param  mixed  $response
     * @return $this
     */
    public function push_response($response): static
    {
        $this->responses[] = $response;
        return $this;
    }
    /**
     * Make the sequence return a default response when it is empty.
     *
     * @param  \GuzzleHttp\Promise\PromiseInterface|\Closure  $response
     * @return $this
     */
    public function when_empty($response): static
    {
        $this->fail_when_empty = false;
        $this->empty_response = $response;
        return $this;
    }
    /**
     * Make the sequence return a default response when it is empty.
     *
     * @return $this
     */
    public function dont_fail_when_empty(): static
    {
        return $this->when_empty(Factory::response());
    }
    /**
     * Indicate that this sequence has depleted all of its responses.
     */
    public function is_empty(): bool
    {
        return count($this->responses) === 0;
    }
    /**
     * Get the next response in the sequence.
     *
     * @param  \Illuminate\Http\Client\Request  $request
     * @return mixed
     *
     * @throws \OutOfBoundsException
     */
    public function __invoke($request)
    {
        if ($this->fail_when_empty && $this->is_empty()) {
            throw new OutOfBoundsException('A request was made, but the response sequence is empty.');
        }
        if (!$this->fail_when_empty && $this->is_empty()) {
            return value($this->empty_response ?? Factory::response());
        }
        $response = array_shift($this->responses);
        return $response instanceof Closure ? $response($request) : $response;
    }
}