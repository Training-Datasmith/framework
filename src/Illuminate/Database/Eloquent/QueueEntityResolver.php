<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Contracts\Queue\Entity_Not_Found_Exception;
use Illuminate\Contracts\Queue\Entity_Resolver as EntityResolverContract;
class Queue_Entity_Resolver implements Entity_Resolver_Contract
{
    /**
     * Resolve the entity for the given ID.
     *
     * @param  string  $type
     * @param  mixed  $id
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Queue\EntityNotFoundException
     */
    public function resolve($type, $id)
    {
        $instance = (new $type())->find($id);
        if ($instance) {
            return $instance;
        }
        throw new Entity_Not_Found_Exception($type, $id);
    }
}