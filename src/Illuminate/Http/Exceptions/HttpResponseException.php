<?php

declare (strict_types=1);
namespace Illuminate\Http\Exceptions;

use RuntimeException;
use Symfony\Component\Http_Foundation\Response;
use Throwable;
class Http_Response_Exception extends RuntimeException
{
    /**
     * The underlying response instance.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    protected $response;
    /**
     * Create a new HTTP response exception instance.
     */
    public function __construct(Response $response, ?Throwable $previous = null)
    {
        parent::__construct($previous?->get_message() ?? '', $previous?->get_code() ?? 0, $previous);
        $this->response = $response;
    }
    /**
     * Get the underlying response instance.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get_response()
    {
        return $this->response;
    }
}