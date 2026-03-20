<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json_Api;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
class Json_Api_Request extends Request
{
    /**
     * Cached sparse fieldset.
     */
    protected ?array $cached_sparse_fields = null;
    /**
     * Cached sparse included.
     */
    protected ?array $cached_sparse_included = null;
    /**
     * Get the request's included fields.
     */
    public function sparse_fields(string $key): array
    {
        if (is_null($this->cached_sparse_fields)) {
            $this->cached_sparse_fields = (new Collection($this->array('fields')))->transform(fn($fieldsets): array => empty($fieldsets) ? [] : explode(',', $fieldsets))->all();
        }
        return $this->cached_sparse_fields[$key] ?? [];
    }
    /**
     * Get the request's included relationships.
     */
    public function sparse_included(?string $key = null): ?array
    {
        if (is_null($this->cached_sparse_included)) {
            $included = (string) $this->string('include', '');
            $this->cached_sparse_included = (new Collection(empty($included) ? [] : explode(',', $included)))->transform(function ($item): array {
                $with = null;
                if (str_contains($item, '.')) {
                    [$relation, $with] = explode('.', $item, 2);
                } else {
                    $relation = $item;
                }
                return ['relation' => $relation, 'with' => $with];
            })->map_to_groups(fn($item): array => [$item['relation'] => $item['with']])->to_array();
        }
        if (is_null($key)) {
            return array_keys($this->cached_sparse_included);
        }
        return transform($this->cached_sparse_included[$key] ?? null, fn($value) => Collection::wrap($value)->transform(function ($item) {
            $item = implode('.', Arr::take(explode('.', $item), Json_Api_Resource::$max_relationship_depth - 1));
            return !empty($item) ? $item : null;
        })->filter()->all()) ?? [];
    }
}