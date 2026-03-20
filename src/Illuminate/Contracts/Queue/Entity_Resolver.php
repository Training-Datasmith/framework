<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Queue;

interface Entity_Resolver
{
    /**
     * Resolve the entity for the given ID.
     *
     * @param  string  $type
     * @param  mixed  $id
     * @return mixed
     */
    public function resolve($type, $id);
}