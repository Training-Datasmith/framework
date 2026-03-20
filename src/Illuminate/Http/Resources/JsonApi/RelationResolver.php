<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json_Api;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
/**
 * @internal
 */
class Relation_Resolver
{
    /**
     * The relation resolver.
     *
     * @var \Closure(mixed):(\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null)
     */
    public Closure $relation_resolver;
    /**
     * The relation resource class.
     *
     * @var class-string<\Illuminate\Http\Resources\JsonApi\JsonApiResource>|null
     */
    public ?string $relation_resource_class = null;
    /**
     * Construct a new resource relationship resolver.
     *
     * @param  \Closure(mixed):(\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null)|class-string<\Illuminate\Http\Resources\JsonApi\JsonApiResource>|null  $resolver
     */
    public function __construct(public string $relation_name, Closure|string|null $resolver = null)
    {
        $this->relation_resolver = match (true) {
            $resolver instanceof Closure => $resolver,
            default => fn($resource) => $resource->get_relation($this->relation_name),
        };
        if (is_string($resolver) && class_exists($resolver)) {
            $this->relation_resource_class = $resolver;
        }
    }
    /**
     * Resolve the relation for a resource.
     */
    public function handle(mixed $resource): Collection|Model|null
    {
        return value($this->relation_resolver, $resource);
    }
    /**
     * Get the resource class.
     *
     * @return class-string<\Illuminate\Http\Resources\JsonApi\JsonApiResource>|null
     */
    public function resource_class(): ?string
    {
        return $this->relation_resource_class;
    }
}