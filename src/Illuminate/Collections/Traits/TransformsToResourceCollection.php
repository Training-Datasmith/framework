<?php

declare (strict_types=1);
namespace Illuminate\Support\Traits;

use Illuminate\Database\Eloquent\Attributes\Use_Resource;
use Illuminate\Database\Eloquent\Attributes\Use_Resource_Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\Resource_Collection;
use LogicException;
use ReflectionClass;
trait Transforms_To_Resource_Collection
{
    /**
     * Create a new resource collection instance for the given resource.
     *
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>|null  $resourceClass
     *
     * @throws \Throwable
     */
    public function to_resource_collection(?string $resource_class = null): Resource_Collection
    {
        if ($resource_class === null) {
            return $this->guess_resource_collection();
        }
        return $resource_class::collection($this);
    }
    /**
     * Guess the resource collection for the items.
     *
     *
     * @throws \Throwable
     */
    protected function guess_resource_collection(): Resource_Collection
    {
        if ($this->is_empty()) {
            return new Resource_Collection($this);
        }
        $model = $this->items[0] ?? null;
        throw_unless(is_object($model), LogicException::class, 'Resource collection guesser expects the collection to contain objects.');
        /** @var class-string<Model> $className */
        $class_name = $model::class;
        throw_unless(method_exists($class_name, 'guessResourceName'), LogicException::class, sprintf('Expected class %s to implement guessResourceName method. Make sure the model uses the TransformsToResource trait.', $class_name));
        $use_resource_collection = $this->resolve_resource_collection_from_attribute($class_name);
        if ($use_resource_collection !== null && class_exists($use_resource_collection)) {
            return new $use_resource_collection($this);
        }
        $use_resource = $this->resolve_resource_from_attribute($class_name);
        if ($use_resource !== null && class_exists($use_resource)) {
            return $use_resource::collection($this);
        }
        $resource_classes = $class_name::guess_resource_name();
        foreach ($resource_classes as $resource_class) {
            $resource_collection = $resource_class . 'Collection';
            if (class_exists($resource_collection)) {
                return new $resource_collection($this);
            }
        }
        foreach ($resource_classes as $resource_class) {
            if (is_string($resource_class) && class_exists($resource_class)) {
                return $resource_class::collection($this);
            }
        }
        throw new LogicException(sprintf('Failed to find resource class for model [%s].', $class_name));
    }
    /**
     * Get the resource class from the class attribute.
     *
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>  $class
     * @return class-string<*>|null
     */
    protected function resolve_resource_from_attribute(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }
        $attributes = (new ReflectionClass($class))->get_attributes(Use_Resource::class);
        return $attributes !== [] ? $attributes[0]->new_instance()->class : null;
    }
    /**
     * Get the resource collection class from the class attribute.
     *
     * @param  class-string<\Illuminate\Http\Resources\Json\ResourceCollection>  $class
     * @return class-string<*>|null
     */
    protected function resolve_resource_collection_from_attribute(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }
        $attributes = (new ReflectionClass($class))->get_attributes(Use_Resource_Collection::class);
        return $attributes !== [] ? $attributes[0]->new_instance()->class : null;
    }
}