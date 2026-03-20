<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
class Resource_Response implements Responsable
{
    /**
     * Create a new resource response.
     *
     * @param  mixed  $resource
     */
    public function __construct(
        /**
         * The underlying resource.
         */
        public $resource
    )
    {
    }
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function to_response($request)
    {
        return tap(response()->json($this->wrap($this->resource->resolve($request), $this->resource->with($request), $this->resource->additional), $this->calculate_status(), [], $this->resource->json_options()), function ($response) use ($request): void {
            $response->original = $this->resource->resource;
            $this->resource->with_response($request, $response);
        });
    }
    /**
     * Wrap the given data if necessary.
     *
     * @param  \Illuminate\Support\Collection|array  $data
     * @param  array  $with
     * @param  array  $additional
     */
    protected function wrap($data, $with = [], $additional = []): array
    {
        if ($data instanceof Collection) {
            $data = $data->all();
        }
        if ($this->have_default_wrapper_and_data_is_unwrapped($data)) {
            $data = [$this->wrapper() => $data];
        } elseif ($this->have_additional_information_and_data_is_unwrapped($data, $with, $additional)) {
            $data = [$this->wrapper() ?? 'data' => $data];
        }
        return array_merge_recursive($data, $with, $additional);
    }
    /**
     * Determine if we have a default wrapper and the given data is unwrapped.
     *
     * @param  array  $data
     */
    protected function have_default_wrapper_and_data_is_unwrapped($data): bool
    {
        if ($this->resource instanceof Json_Resource && $this->resource::$force_wrapping) {
            return $this->wrapper() !== null;
        }
        return $this->wrapper() && !array_key_exists($this->wrapper(), $data);
    }
    /**
     * Determine if "with" data has been added and our data is unwrapped.
     *
     * @param  array  $data
     * @param  array  $with
     * @param  array  $additional
     */
    protected function have_additional_information_and_data_is_unwrapped($data, $with, $additional): bool
    {
        return (!empty($with) || !empty($additional)) && (!$this->wrapper() || !array_key_exists($this->wrapper(), $data));
    }
    /**
     * Get the default data wrapper for the resource.
     *
     * @return string
     */
    protected function wrapper()
    {
        return $this->resource::class::$wrap;
    }
    /**
     * Calculate the appropriate status code for the response.
     */
    protected function calculate_status(): int
    {
        return $this->resource->resource instanceof Model && $this->resource->resource->was_recently_created ? 201 : 200;
    }
}