<?php

declare (strict_types=1);
namespace Illuminate\Container\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\Contextual_Attribute;
use Illuminate\Log\Context\Repository;
#[Attribute(Attribute::TARGET_PARAMETER)]
class Context implements Contextual_Attribute
{
    /**
     * Create a new attribute instance.
     */
    public function __construct(public string $key, public mixed $default = null, public bool $hidden = false)
    {
    }
    /**
     * Resolve the context value.
     */
    public static function resolve(self $attribute, Container $container): mixed
    {
        $repository = $container->make(Repository::class);
        return match ($attribute->hidden) {
            true => $repository->get_hidden($attribute->key, $attribute->default),
            false => $repository->get($attribute->key, $attribute->default),
        };
    }
}