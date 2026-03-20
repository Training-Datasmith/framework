<?php

declare (strict_types=1);
namespace Illuminate\Http;

use Illuminate\Http\Exceptions\Http_Response_Exception;
use Symfony\Component\Http_Foundation\Header_Bag;
use Throwable;
trait Response_Trait
{
    /**
     * The original content of the response.
     *
     * @var mixed
     */
    public $original;
    /**
     * The exception that triggered the error response (if applicable).
     *
     * @var \Throwable|null
     */
    public $exception;
    /**
     * Get the status code for the response.
     *
     * @return int
     */
    public function status()
    {
        return $this->get_status_code();
    }
    /**
     * Get the status text for the response.
     *
     * @return string
     */
    public function status_text()
    {
        return $this->status_text;
    }
    /**
     * Get the content of the response.
     *
     * @return string
     */
    public function content()
    {
        return $this->get_content();
    }
    /**
     * Get the original response content.
     *
     * @return mixed
     */
    public function get_original_content()
    {
        $original = $this->original;
        return $original instanceof self ? $original->{__FUNCTION__}() : $original;
    }
    /**
     * Set a header on the Response.
     *
     * @param  string  $key
     * @param  array|string  $values
     * @param  bool  $replace
     * @return $this
     */
    public function header($key, $values, $replace = true)
    {
        $this->headers->set($key, $values, $replace);
        return $this;
    }
    /**
     * Add an array of headers to the response.
     *
     * @param  \Symfony\Component\HttpFoundation\HeaderBag|array  $headers
     * @return $this
     */
    public function with_headers($headers)
    {
        if ($headers instanceof Header_Bag) {
            $headers = $headers->all();
        }
        foreach ($headers as $key => $value) {
            $this->headers->set($key, $value);
        }
        return $this;
    }
    /**
     * Remove a header(s) from the response.
     *
     * @param  array|string  $key
     * @return $this
     */
    public function without_header($key)
    {
        foreach ((array) $key as $header) {
            $this->headers->remove($header);
        }
        return $this;
    }
    /**
     * Add a cookie to the response.
     *
     * @param  \Symfony\Component\HttpFoundation\Cookie|mixed  $cookie
     * @return $this
     */
    public function cookie($cookie)
    {
        return $this->with_cookie(...func_get_args());
    }
    /**
     * Add a cookie to the response.
     *
     * @param  \Symfony\Component\HttpFoundation\Cookie|mixed  $cookie
     * @return $this
     */
    public function with_cookie($cookie)
    {
        if (is_string($cookie) && function_exists('cookie')) {
            $cookie = cookie(...func_get_args());
        }
        $this->headers->set_cookie($cookie);
        return $this;
    }
    /**
     * Expire a cookie when sending the response.
     *
     * @param  \Symfony\Component\HttpFoundation\Cookie|mixed  $cookie
     * @param  string|null  $path
     * @param  string|null  $domain
     * @return $this
     */
    public function without_cookie($cookie, $path = null, $domain = null)
    {
        if (is_string($cookie) && function_exists('cookie')) {
            $cookie = cookie($cookie, null, -2628000, $path, $domain);
        }
        $this->headers->set_cookie($cookie);
        return $this;
    }
    /**
     * Get the callback of the response.
     *
     * @return string|null
     */
    public function get_callback()
    {
        return $this->callback ?? null;
    }
    /**
     * Set the exception to attach to the response.
     *
     * @return $this
     */
    public function with_exception(Throwable $e)
    {
        $this->exception = $e;
        return $this;
    }
    /**
     * Throws the response in a HttpResponseException instance.
     *
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function throw_response(): never
    {
        throw new Http_Response_Exception($this);
    }
}