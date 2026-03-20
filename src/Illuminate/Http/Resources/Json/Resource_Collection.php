<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json;

use Countable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Collects_Resources;
use Illuminate\Pagination\Abstract_Cursor_Paginator;
use Illuminate\Pagination\Abstract_Paginator;
use IteratorAggregate;
class Resource_Collection extends Json_Resource implements Countable, IteratorAggregate
{
    use Collects_Resources;
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects;
    /**
     * The mapped collection instance.
     *
     * @var \Illuminate\Support\Collection|null
     */
    public $collection;
    /**
     * Indicates if all existing request query parameters should be added to pagination links.
     *
     * @var bool
     */
    protected $preserve_all_query_parameters = false;
    /**
     * The query parameters that should be added to the pagination links.
     *
     * @var array|null
     */
    protected $query_parameters;
    /**
     * Create a new resource instance.
     *
     * @param  mixed  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->resource = $this->collect_resource($resource);
    }
    /**
     * Indicate that all current query parameters should be appended to pagination links.
     *
     * @return $this
     */
    public function preserve_query(): static
    {
        $this->preserve_all_query_parameters = true;
        return $this;
    }
    /**
     * Specify the query string parameters that should be present on pagination links.
     *
     * @return $this
     */
    public function with_query(array $query): static
    {
        $this->preserve_all_query_parameters = false;
        $this->query_parameters = $query;
        return $this;
    }
    /**
     * Return the count of items in the resource collection.
     */
    public function count(): int
    {
        return $this->collection->count();
    }
    /**
     * Transform the resource into a JSON array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    #[\Override]
    public function to_array(Request $request)
    {
        if ($this->collection->first() instanceof Json_Resource) {
            return $this->collection->map->resolve($request)->all();
        }
        return $this->collection->map->to_array()->all();
    }
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function to_response($request)
    {
        if ($this->resource instanceof Abstract_Paginator || $this->resource instanceof Abstract_Cursor_Paginator) {
            return $this->prepare_paginated_response($request);
        }
        return parent::to_response($request);
    }
    /**
     * Create a paginate-aware HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function prepare_paginated_response($request)
    {
        if ($this->preserve_all_query_parameters) {
            $this->resource->appends($request->query());
        } elseif (!is_null($this->query_parameters)) {
            $this->resource->appends($this->query_parameters);
        }
        return (new Paginated_Resource_Response($this))->to_response($request);
    }
}