<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Routing;

interface Url_Routable
{
    /**
     * Get the value of the model's route key.
     *
     * @return mixed
     */
    public function get_route_key();
    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function get_route_key_name();
    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolve_route_binding($value, $field = null);
    /**
     * Retrieve the child model for a bound value.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolve_child_route_binding($child_type, $value, $field);
}