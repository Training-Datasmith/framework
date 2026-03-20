<?php

declare (strict_types=1);
namespace Illuminate\Container\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\Contextual_Attribute;
#[Attribute(Attribute::TARGET_PARAMETER)]
class Give implements Contextual_Attribute
{
    /**
     * Provide a concrete class implementation for dependency injection.
     *
     * @param  array|null  $params
     */
    public function __construct(public string $class, public array $params = [])
    {
    }
    /**
     * Resolve the dependency.
     */
    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make($attribute->class, $attribute->params);
    }
}