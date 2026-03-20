<?php

declare (strict_types=1);
namespace Illuminate\Container\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\Contextual_Attribute;
#[Attribute(Attribute::TARGET_PARAMETER)]
class Authenticated implements Contextual_Attribute
{
    /**
     * Create a new class instance.
     */
    public function __construct(public ?string $guard = null)
    {
    }
    /**
     * Resolve the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public static function resolve(self $attribute, Container $container): mixed
    {
        return call_user_func($container->make('auth')->user_resolver(), $attribute->guard);
    }
}