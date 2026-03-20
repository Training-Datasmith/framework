<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources;

use Illuminate\Http\Resources\Json\Json_Resource;
use Illuminate\Pagination\Abstract_Cursor_Paginator;
use Illuminate\Pagination\Abstract_Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
use Traversable;
trait Collects_Resources
{
    /**
     * Map the given collection resource into its individual resources.
     *
     * @param  mixed  $resource
     * @return mixed
     */
    protected function collect_resource($resource)
    {
        if ($resource instanceof Missing_Value) {
            return $resource;
        }
        if (is_array($resource)) {
            $resource = new Collection($resource);
        }
        $collects = $this->collects();
        $this->collection = $collects && !$resource->first() instanceof $collects ? $resource->map_into($collects) : $resource->to_base();
        return $resource instanceof Abstract_Paginator || $resource instanceof Abstract_Cursor_Paginator ? $resource->set_collection($this->collection) : $this->collection;
    }
    /**
     * Get the resource that this resource collects.
     *
     * @return class-string<\Illuminate\Http\Resources\Json\JsonResource>|null
     *
     * @throws \LogicException
     */
    protected function collects(): \Illuminate\Http\Resources\Json\Json_Resource|string|null
    {
        $collects = null;
        if ($this->collects) {
            $collects = $this->collects;
        } elseif (str_ends_with(class_basename($this), 'Collection') && (class_exists($class = Str::replace_last('Collection', '', $this::class)) || class_exists($class = Str::replace_last('Collection', 'Resource', $this::class)))) {
            $collects = $class;
        }
        if (!$collects || is_a($collects, Json_Resource::class, true)) {
            return $collects;
        }
        throw new LogicException('Resource collections must collect instances of ' . Json_Resource::class . '.');
    }
    /**
     * Get the JSON serialization options that should be applied to the resource response.
     *
     * @return int
     *
     * @throws \ReflectionException
     */
    public function json_options()
    {
        $collects = $this->collects();
        if (!$collects) {
            return 0;
        }
        return (new ReflectionClass($collects))->new_instance_without_constructor()->json_options();
    }
    /**
     * Get an iterator for the resource collection.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return $this->collection->getIterator();
    }
}