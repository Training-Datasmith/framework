<?php

declare (strict_types=1);
namespace Illuminate\Container\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\Contextual_Attribute;
#[Attribute(Attribute::TARGET_PARAMETER)]
class Route_Parameter implements Contextual_Attribute
{
    /**
     * Create a new class instance.
     */
    public function __construct(public string $parameter)
    {
    }
    /**
     * Resolve the route parameter.
     *
     * @return mixed
     */
    public static function resolve(self $attribute, Container $container)
    {
        return $container->make('request')->route($attribute->parameter);
    }
}