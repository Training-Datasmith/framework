<?php

declare (strict_types=1);
namespace Illuminate\Container\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\Contextual_Attribute;
use Unit_Enum;
#[Attribute(Attribute::TARGET_PARAMETER)]
class Database implements Contextual_Attribute
{
    /**
     * Create a new class instance.
     */
    public function __construct(public Unit_Enum|string|null $connection = null)
    {
    }
    /**
     * Resolve the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    public static function resolve(self $attribute, Container $container)
    {
        return $container->make('db')->connection($attribute->connection);
    }
}