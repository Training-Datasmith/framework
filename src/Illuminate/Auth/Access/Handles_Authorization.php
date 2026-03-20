<?php

declare (strict_types=1);
namespace Illuminate\Auth\Access;

trait Handles_Authorization
{
    /**
     * Create a new access response.
     *
     * @param  string|null  $message
     * @param  mixed  $code
     */
    protected function allow($message = null, $code = null): \Illuminate\Auth\Access\Response
    {
        return Response::allow($message, $code);
    }
    /**
     * Throws an unauthorized exception.
     *
     * @param  string|null  $message
     * @param  mixed  $code
     */
    protected function deny($message = null, $code = null): \Illuminate\Auth\Access\Response
    {
        return Response::deny($message, $code);
    }
    /**
     * Deny with a HTTP status code.
     *
     * @param  int  $status
     * @param  string|null  $message
     * @param  int|null  $code
     * @return \Illuminate\Auth\Access\Response
     */
    public function deny_with_status($status, $message = null, $code = null)
    {
        return Response::deny_with_status($status, $message, $code);
    }
    /**
     * Deny with a 404 HTTP status code.
     *
     * @param  string|null  $message
     * @param  int|null  $code
     * @return \Illuminate\Auth\Access\Response
     */
    public function deny_as_not_found($message = null, $code = null)
    {
        return Response::deny_with_status(404, $message, $code);
    }
}