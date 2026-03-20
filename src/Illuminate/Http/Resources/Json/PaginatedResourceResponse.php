<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json;

use Illuminate\Support\Arr;
class Paginated_Resource_Response extends Resource_Response
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function to_response($request)
    {
        return tap(response()->json($this->wrap($this->resource->resolve($request), array_merge_recursive($this->pagination_information($request), $this->resource->with($request), $this->resource->additional)), $this->calculate_status(), [], $this->resource->json_options()), function ($response) use ($request): void {
            $response->original = $this->resource->resource->map(function ($item) {
                if (is_array($item)) {
                    return Arr::get($item, 'resource');
                }
                if (is_object($item)) {
                    return $item->resource ?? null;
                }
                return null;
            });
            $this->resource->with_response($request, $response);
        });
    }
    /**
     * Add the pagination information to the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function pagination_information($request)
    {
        $paginated = $this->resource->resource->to_array();
        $default = ['links' => $this->pagination_links($paginated), 'meta' => $this->meta($paginated)];
        if (method_exists($this->resource, 'paginationInformation') || $this->resource->has_macro('paginationInformation')) {
            return $this->resource->pagination_information($request, $paginated, $default);
        }
        return $default;
    }
    /**
     * Get the pagination links for the response.
     */
    protected function pagination_links(array $paginated): array
    {
        return ['first' => $paginated['first_page_url'] ?? null, 'last' => $paginated['last_page_url'] ?? null, 'prev' => $paginated['prev_page_url'] ?? null, 'next' => $paginated['next_page_url'] ?? null];
    }
    /**
     * Gather the metadata for the response.
     */
    protected function meta(array $paginated): array
    {
        return Arr::except($paginated, ['data', 'first_page_url', 'last_page_url', 'prev_page_url', 'next_page_url']);
    }
}