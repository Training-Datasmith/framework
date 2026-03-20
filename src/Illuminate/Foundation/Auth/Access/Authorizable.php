<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Auth\Access;

use Illuminate\Contracts\Auth\Access\Gate;
trait Authorizable
{
    /**
     * Determine if the entity has the given abilities.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     * @return bool
     */
    public function can($abilities, $arguments = [])
    {
        return app(Gate::class)->for_user($this)->check($abilities, $arguments);
    }
    /**
     * Determine if the entity has any of the given abilities.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     * @return bool
     */
    public function can_any($abilities, $arguments = [])
    {
        return app(Gate::class)->for_user($this)->any($abilities, $arguments);
    }
    /**
     * Determine if the entity does not have the given abilities.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     */
    public function cant($abilities, $arguments = []): bool
    {
        return !$this->can($abilities, $arguments);
    }
    /**
     * Determine if the entity does not have the given abilities.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  mixed  $arguments
     * @return bool
     */
    public function cannot($abilities, $arguments = [])
    {
        return $this->cant($abilities, $arguments);
    }
}