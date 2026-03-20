<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Routing;

interface Binding_Registrar
{
    /**
     * Add a new route parameter binder.
     *
     * @param  string  $key
     * @param  string|callable  $binder
     * @return void
     */
    public function bind($key, $binder);
    /**
     * Get the binding callback for a given binding.
     *
     * @param  string  $key
     * @return \Closure
     */
    public function get_binding_callback($key);
}