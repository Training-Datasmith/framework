<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json;

use ArrayAccess;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\Url_Routable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Json_Encoding_Exception;
use Illuminate\Http\Json_Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Conditionally_Loads_Attributes;
use Illuminate\Http\Resources\Delegates_To_Resource;
use Json_Exception;
use JsonSerializable;
class Json_Resource implements ArrayAccess, JsonSerializable, Responsable, Url_Routable
{
    use Conditionally_Loads_Attributes;
    use Delegates_To_Resource;
    /**
     * The resource instance.
     *
     * @var mixed
     */
    public $resource;
    /**
     * The additional data that should be added to the top-level resource array.
     *
     * @var array
     */
    public $with = [];
    /**
     * The additional metadata that should be added to the resource response.
     *
     * Added during response construction by the developer.
     *
     * @var array
     */
    public $additional = [];
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = 'data';
    /**
     * Whether to force wrapping even if the $wrap key exists in underlying resource data.
     */
    public static bool $force_wrapping = false;
    /**
     * Create a new resource instance.
     *
     * @param  mixed  $resource
     */
    public function __construct($resource)
    {
        $this->resource = $resource;
    }
    /**
     * Create a new resource instance.
     *
     * @param  mixed  ...$parameters
     */
    public static function make(...$parameters): static
    {
        return new static(...$parameters);
    }
    /**
     * Create a new anonymous resource collection.
     *
     * @param  mixed  $resource
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public static function collection($resource)
    {
        return tap(static::new_collection($resource), function ($collection): void {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserve_keys = (new static([]))->preserve_keys === true;
            }
        });
    }
    /**
     * Create a new resource collection instance.
     *
     * @param  mixed  $resource
     */
    protected static function new_collection($resource): \Illuminate\Http\Resources\Json\Anonymous_Resource_Collection
    {
        return new Anonymous_Resource_Collection($resource, static::class);
    }
    /**
     * Resolve the resource to an array.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return array
     */
    public function resolve($request = null)
    {
        $data = $this->resolve_resource_data($request ?: $this->resolve_request_from_container());
        if ($data instanceof Arrayable) {
            $data = $data->to_array();
        } elseif ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }
        return $this->filter((array) $data);
    }
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function to_attributes(Request $request)
    {
        if (property_exists($this, 'attributes')) {
            return $this->attributes;
        }
        return $this->to_array($request);
    }
    /**
     * Resolve the resource data to an array.
     *
     * @return array
     */
    public function resolve_resource_data(Request $request)
    {
        return $this->to_attributes($request);
    }
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function to_array(Request $request)
    {
        if (is_null($this->resource)) {
            return [];
        }
        return is_array($this->resource) ? $this->resource : $this->resource->to_array();
    }
    /**
     * Convert the resource to JSON.
     *
     * @param  int  $options
     * @return string
     *
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function to_json($options = 0)
    {
        try {
            $json = json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (Json_Exception $e) {
            throw Json_Encoding_Exception::for_resource($this, $e->get_message());
        }
        return $json;
    }
    /**
     * Convert the resource to pretty print formatted JSON.
     *
     * @return string
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function to_pretty_json(int $options = 0)
    {
        return $this->to_json(JSON_PRETTY_PRINT | $options);
    }
    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @return array
     */
    public function with(Request $request)
    {
        return $this->with;
    }
    /**
     * Add additional metadata to the resource response.
     *
     * @return $this
     */
    public function additional(array $data): static
    {
        $this->additional = $data;
        return $this;
    }
    /**
     * Get the JSON serialization options that should be applied to the resource response.
     */
    public function json_options(): int
    {
        return 0;
    }
    /**
     * Customize the response for a request.
     */
    public function with_response(Request $request, Json_Response $response): void
    {
    }
    /**
     * Resolve the HTTP request instance from container.
     *
     * @return \Illuminate\Http\Request
     */
    protected function resolve_request_from_container()
    {
        return Container::get_instance()->make('request');
    }
    /**
     * Set the string that should wrap the outer-most resource array.
     *
     * @param  string  $value
     */
    public static function wrap($value): void
    {
        static::$wrap = $value;
    }
    /**
     * Disable wrapping of the outer-most resource array.
     */
    public static function without_wrapping(): void
    {
        static::$wrap = null;
    }
    /**
     * Transform the resource into an HTTP response.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function response($request = null)
    {
        return $this->to_response($request ?: $this->resolve_request_from_container());
    }
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function to_response($request)
    {
        return (new Resource_Response($this))->to_response($request);
    }
    /**
     * Prepare the resource for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return $this->resolve($this->resolve_request_from_container());
    }
    /**
     * Flush the resource's global state.
     */
    public static function flush_state(): void
    {
        static::$wrap = 'data';
        static::$force_wrapping = false;
    }
}