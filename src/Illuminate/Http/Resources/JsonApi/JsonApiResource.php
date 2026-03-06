<?php

declare(strict_types=1);

namespace Illuminate\Http\Resources\JsonApi;

use BadMethodCallException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class JsonApiResource extends JsonResource
{
    use Concerns\ResolvesJsonApiElements;
    use Concerns\ResolvesJsonApiRequest;

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
    public static $jsonApiInformation = [];

    /**
     * The resource's "links" for JSON:API.
     */
    protected array $jsonApiLinks = [];

    /**
     * The resource's "meta" for JSON:API.
     */
    protected array $jsonApiMeta = [];

    /**
     * Set the JSON:API version for the request.
     */
    public static function configure(?string $version = null, array $ext = [], array $profile = [], array $meta = []): void
    {
        static::$jsonApiInformation = array_filter([
            'version' => $version,
            'ext' => $ext,
            'profile' => $profile,
            'meta' => $meta,
        ]);
    }

    /**
     * Get the resource's ID.
     *
     * @return string|null
     */
    public function toId(Request $request): null
    {
        return null;
    }

    /**
     * Get the resource's type.
     *
     * @return string|null
     */
    public function toType(Request $request): null
    {
        return null;
    }

    /**
     * Transform the resource into an array.
     *
     * @return \Illuminate\Contracts\Support\Arrayable|\JsonSerializable|array
     */
    #[\Override]
    public function toAttributes(Request $request)
    {
        if (property_exists($this, 'attributes')) {
            return $this->attributes;
        }

        return $this->toArray($request);
    }

    /**
     * Get the resource's relationships.
     *
     * @return \Illuminate\Contracts\Support\Arrayable|array
     */
    public function toRelationships(Request $request)
    {
        if (property_exists($this, 'relationships')) {
            return $this->relationships;
        }

        return [];
    }

    /**
     * Get the resource's links.
     */
    public function toLinks(Request $request): array
    {
        return $this->jsonApiLinks;
    }

    /**
     * Get the resource's meta information.
     */
    public function toMeta(Request $request): array
    {
        return $this->jsonApiMeta;
    }

    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    #[\Override]
    public function with($request): array
    {
        return array_filter([
            'included' => $this->resolveIncludedResourceObjects($request)
                ->uniqueStrict('_uniqueKey')
                ->map(fn (array $included): array => Arr::except($included, ['_uniqueKey']))
                ->values()
                ->all(),
            ...($implementation = static::$jsonApiInformation)
                ? ['jsonapi' => $implementation]
                : [],
        ]);
    }

    /**
     * Resolve the resource to an array.
     *
     * @param  \Illuminate\Http\Request|null  $request
     */
    #[\Override]
    public function resolve($request = null): array
    {
        return [
            'data' => $this->resolveResourceData($this->resolveJsonApiRequestFrom($request ?? $this->resolveRequestFromContainer())),
        ];
    }

    /**
     * Resolve the resource data to an array.
     */
    #[\Override]
    public function resolveResourceData(Request $request): array
    {
        return $this->resolveResourceObject($request);
    }

    /**
     * Customize the outgoing response for the resource.
     */
    #[\Override]
    public function withResponse(Request $request, JsonResponse $response): void
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
    public function toResponse($request)
    {
        return parent::toResponse($this->resolveJsonApiRequestFrom($request));
    }

    /**
     * Resolve the HTTP request instance from container.
     *
     * @return \Illuminate\Http\Resources\JsonApi\JsonApiRequest
     */
    #[\Override]
    protected function resolveRequestFromContainer()
    {
        return $this->resolveJsonApiRequestFrom(parent::resolveRequestFromContainer());
    }

    /**
     * Create a new resource collection instance.
     *
     * @param  mixed  $resource
     */
    #[\Override]
    protected static function newCollection($resource): \Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection
    {
        return new AnonymousResourceCollection($resource, static::class);
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
    public static function withoutWrapping(): never
    {
        throw new BadMethodCallException(sprintf('Using %s() method is not allowed.', __METHOD__));
    }

    /**
     * Flush the resource's global state.
     */
    #[\Override]
    public static function flushState(): void
    {
        parent::flushState();

        static::$jsonApiInformation = [];
        static::$maxRelationshipDepth = 3;
    }
}
