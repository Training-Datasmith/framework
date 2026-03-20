<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Translation;

interface Loader
{
    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null);
    /**
     * Add a new namespace to the loader.
     *
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function add_namespace($namespace, $hint);
    /**
     * Add a new JSON path to the loader.
     *
     * @param  string  $path
     * @return void
     */
    public function add_json_path($path);
    /**
     * Get an array of all the registered namespaces.
     *
     * @return array
     */
    public function namespaces();
}