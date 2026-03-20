<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Attributes\Use_Resource;
use Illuminate\Http\Resources\Json\Json_Resource;
use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
trait Transforms_To_Resource
{
    /**
     * Create a new resource object for the given resource.
     *
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>|null  $resourceClass
     */
    public function to_resource(?string $resource_class = null): Json_Resource
    {
        if ($resource_class === null) {
            return $this->guess_resource();
        }
        return $resource_class::make($this);
    }
    /**
     * Guess the resource class for the model.
     */
    protected function guess_resource(): Json_Resource
    {
        $resource_class = $this->resolve_resource_from_attribute(static::class);
        if ($resource_class !== null && class_exists($resource_class)) {
            return $resource_class::make($this);
        }
        foreach (static::guess_resource_name() as $resource_class) {
            if (is_string($resource_class) && class_exists($resource_class)) {
                return $resource_class::make($this);
            }
        }
        throw new LogicException(sprintf('Failed to find resource class for model [%s].', $this::class));
    }
    /**
     * Guess the resource class name for the model.
     *
     * @return array{class-string<\Illuminate\Http\Resources\Json\JsonResource>, class-string<\Illuminate\Http\Resources\Json\JsonResource>}
     */
    public static function guess_resource_name(): array
    {
        $model_class = static::class;
        if (!Str::contains($model_class, '\Models\\')) {
            return [];
        }
        $relative_namespace = Str::after($model_class, '\Models\\');
        $relative_namespace = Str::contains($relative_namespace, '\\') ? Str::before($relative_namespace, '\\' . class_basename($model_class)) : '';
        $potential_resource = sprintf('%s\Http\Resources\%s%s', Str::before($model_class, '\Models'), strlen($relative_namespace) > 0 ? $relative_namespace . '\\' : '', class_basename($model_class));
        return [$potential_resource . 'Resource', $potential_resource];
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
}