<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json_Api;

use BadMethodCallException;
use Illuminate\Http\Json_Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\Json_Resource;
use Illuminate\Support\Arr;
class Json_Api_Resource extends Json_Resource
{
    use Concerns\Resolves_Json_Api_Elements;
    use Concerns\Resolves_Json_Api_Request;
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = 'data';
    /**
     * The resource's "version" for JSON:API.
     *
     * @var array{version?: string, ext?: array, profile?: array, meta?: array}
     */
    public static $json_api_information = [];
    /**
     * The resource's "links" for JSON:API.
     */
    protected array $json_api_links = [];
    /**
     * The resource's "meta" for JSON:API.
     */
    protected array $json_api_meta = [];
    /**
     * Set the JSON:API version for the request.
     */
    public static function configure(?string $version = null, array $ext = [], array $profile = [], array $meta = []): void
    {
        static::$json_api_information = array_filter(['version' => $version, 'ext' => $ext, 'profile' => $profile, 'meta' => $meta]);
    }
    /**
     * Get the resource's ID.
     *
     * @return string|null
     */
    public function to_id(Request $request): null
    {
        return null;
    }
    /**
     * Get the resource's type.
     *
     * @return string|null
     */
    public function to_type(Request $request): null
    {
        return null;
    }
    /**
     * Transform the resource into an array.
     *
     * @return \Illuminate\Contracts\Support\Arrayable|\JsonSerializable|array
     */
    #[\Override]
    public function to_attributes(Request $request)
    {
        if (property_exists($this, 'attributes')) {
            return $this->attributes;
        }
        return $this->to_array($request);
    }
    /**
     * Get the resource's relationships.
     *
     * @return \Illuminate\Contracts\Support\Arrayable|array
     */
    public function to_relationships(Request $request)
    {
        if (property_exists($this, 'relationships')) {
            return $this->relationships;
        }
        return [];
    }
    /**
     * Get the resource's links.
     */
    public function to_links(Request $request): array
    {
        return $this->json_api_links;
    }
    /**
     * Get the resource's meta information.
     */
    public function to_meta(Request $request): array
    {
        return $this->json_api_meta;
    }
    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    #[\Override]
    public function with($request): array
    {
        return array_filter(['included' => $this->resolve_included_resource_objects($request)->unique_strict('_uniqueKey')->map(fn(array $included): array => Arr::except($included, ['_uniqueKey']))->values()->all(), ...($implementation = static::$json_api_information) ? ['jsonapi' => $implementation] : []]);
    }
    /**
     * Resolve the resource to an array.
     *
     * @param  \Illuminate\Http\Request|null  $request
     */
    #[\Override]
    public function resolve($request = null): array
    {
        return ['data' => $this->resolve_resource_data($this->resolve_json_api_request_from($request ?? $this->resolve_request_from_container()))];
    }
    /**
     * Resolve the resource data to an array.
     */
    #[\Override]
    public function resolve_resource_data(Request $request): array
    {
        return $this->resolve_resource_object($request);
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
     * @return \Illuminate\Http\Resources\JsonApi\JsonApiRequest
     */
    #[\Override]
    protected function resolve_request_from_container()
    {
        return $this->resolve_json_api_request_from(parent::resolve_request_from_container());
    }
    /**
     * Create a new resource collection instance.
     *
     * @param  mixed  $resource
     */
    #[\Override]
    protected static function new_collection($resource): \Illuminate\Http\Resources\Json_Api\Anonymous_Resource_Collection
    {
        return new Anonymous_Resource_Collection($resource, static::class);
    }
    /**
     * Set the string that should wrap the outer-most resource array.
     *
     * @param  string  $value
     *
     * @throws \RuntimeException
     */
    #[\Override]
    public static function wrap($value): never
    {
        throw new BadMethodCallException(sprintf('Using %s() method is not allowed.', __METHOD__));
    }
    /**
     * Disable wrapping of the outer-most resource array.
     */
    #[\Override]
    public static function without_wrapping(): never
    {
        throw new BadMethodCallException(sprintf('Using %s() method is not allowed.', __METHOD__));
    }
    /**
     * Flush the resource's global state.
     */
    #[\Override]
    public static function flush_state(): void
    {
        parent::flush_state();
        static::$json_api_information = [];
        static::$max_relationship_depth = 3;
    }
}