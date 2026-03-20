<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Database\Eloquent\Attributes\Collected_By;
use ReflectionClass;
/**
 * @template TCollection of \Illuminate\Database\Eloquent\Collection
 */
trait Has_Collection
{
    /**
     * The resolved collection class names by model.
     *
     * @var array<class-string<static>, class-string<TCollection>>
     */
    protected static array $resolved_collection_classes = [];
    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<array-key, \Illuminate\Database\Eloquent\Model>  $models
     * @return TCollection
     */
    public function new_collection(array $models = []): object
    {
        static::$resolved_collection_classes[static::class] ??= $this->resolve_collection_from_attribute() ?? static::$collection_class;
        $collection = new static::$resolved_collection_classes[static::class]($models);
        if (Model::is_automatically_eager_loading_relationships()) {
            $collection->with_relationship_autoloading();
        }
        return $collection;
    }
    /**
     * Resolve the collection class name from the CollectedBy attribute.
     *
     * @return class-string<TCollection>|null
     */
    public function resolve_collection_from_attribute()
    {
        $reflection_class = new ReflectionClass(static::class);
        $attributes = $reflection_class->get_attributes(Collected_By::class);
        if (!isset($attributes[0]) || !isset($attributes[0]->get_arguments()[0])) {
            return;
        }
        return $attributes[0]->get_arguments()[0];
    }
}