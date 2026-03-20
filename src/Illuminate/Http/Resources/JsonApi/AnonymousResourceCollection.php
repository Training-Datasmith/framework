<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json_Api;

use Illuminate\Container\Container;
use Illuminate\Http\Json_Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
class Anonymous_Resource_Collection extends \Illuminate\Http\Resources\Json\Anonymous_Resource_Collection
{
    use Concerns\Resolves_Json_Api_Request;
    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    #[\Override]
    public function with($request): array
    {
        return array_filter(['included' => $this->collection->map(fn($resource) => $resource->resolve_included_resource_objects($request))->flatten(depth: 1)->unique_strict('_uniqueKey')->map(fn(array $included): array => Arr::except($included, ['_uniqueKey']))->values()->all(), ...($implementation = Json_Api_Resource::$json_api_information) ? ['jsonapi' => $implementation] : []]);
    }
    /**
     * Transform the resource into a JSON array.
     *
     * @return array
     */
    #[\Override]
    public function to_attributes(Request $request)
    {
        return $this->collection->map(fn($resource) => $resource->resolve_resource_data($request))->all();
    }
    /**
     * Customize the outgoing response for the resource.
     */
    #[\Override]
    public function with_response(Request $request, Json_Response $response): void
    {
        $response->header('Content-Type', 'application/vnd.api+json');
    }
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[\Override]
    public function to_response($request)
    {
        return parent::to_response($this->resolve_json_api_request_from($request));
    }
    /**
     * Resolve the HTTP request instance from container.
     *
     * @return \Illuminate\Http\Resources\JsonApi\SparseRequest
     */
    #[\Override]
    protected function resolve_request_from_container()
    {
        return $this->resolve_json_api_request_from(Container::get_instance()->make('request'));
    }
}