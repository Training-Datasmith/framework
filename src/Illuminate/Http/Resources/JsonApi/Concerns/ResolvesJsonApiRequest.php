<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json_Api\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json_Api\Json_Api_Request;
trait Resolves_Json_Api_Request
{
    /**
     * Resolve a JSON API request instance from the given HTTP request.
     *
     * @return \Illuminate\Http\Resources\JsonApi\JsonApiRequest
     */
    protected function resolve_json_api_request_from(Request $request)
    {
        return $request instanceof Json_Api_Request ? $request : Json_Api_Request::create_from($request);
    }
}