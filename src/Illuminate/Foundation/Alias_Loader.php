<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

class Alias_Loader
{
    /**
     * Indicates if a loader has been registered.
     *
     * @var bool
     */
    protected $registered = false;
    /**
     * The namespace for all real-time facades.
     *
     * @var string
     */
    protected static $facade_namespace = 'Facades\\';
    /**
     * The singleton instance of the loader.
     *
     * @var \Illuminate\Foundation\AliasLoader
     */
    protected static $instance;
    /**
     * Create a new AliasLoader instance.
     *
     * @param  array  $aliases
     */
    private function __construct(
        /**
         * The array of class aliases.
         */
        protected $aliases
    )
    {
    }
    /**
     * Get or create the singleton alias loader instance.
     *
     * @return \Illuminate\Foundation\AliasLoader
     */
    public static function get_instance(array $aliases = [])
    {
        if (is_null(static::$instance)) {
            return static::$instance = new static($aliases);
        }
        $aliases = array_merge(static::$instance->get_aliases(), $aliases);
        static::$instance->set_aliases($aliases);
        return static::$instance;
    }
    /**
     * Load a class alias if it is registered.
     *
     * @param  string  $alias
     * @return bool|null
     */
    public function load($alias)
    {
        if (static::$facade_namespace && str_starts_with($alias, static::$facade_namespace)) {
            $this->load_facade($alias);
            return true;
        }
        if (isset($this->aliases[$alias])) {
            return class_alias($this->aliases[$alias], $alias);
        }
    }
    /**
     * Load a real-time facade for the given alias.
     *
     * @param  string  $alias
     * @return void
     */
    protected function load_facade($alias)
    {
        require $this->ensure_facade_exists($alias);
    }
    /**
     * Ensure that the given alias has an existing real-time facade class.
     *
     * @param  string  $alias
     */
    protected function ensure_facade_exists($alias): string
    {
        if (is_file($path = storage_path('framework/cache/facade-' . sha1($alias) . '.php'))) {
            return $path;
        }
        $stub = $this->format_facade_stub($alias, file_get_contents(__DIR__ . '/stubs/facade.stub'));
        // Atomic write to prevent race conditions...
        $temp_path = tempnam(dirname($path), 'facade-');
        // Fix permissions of tempPath because `tempnam()` creates it with permissions set to 0600...
        chmod($temp_path, 0777 - umask());
        file_put_contents($temp_path, $stub);
        rename($temp_path, $path);
        return $path;
    }
    /**
     * Format the facade stub with the proper namespace and class.
     *
     * @param  string  $alias
     * @param  string  $stub
     */
    protected function format_facade_stub($alias, $stub): string
    {
        $replacements = [str_replace('/', '\\', dirname(str_replace('\\', '/', $alias))), class_basename($alias), substr($alias, strlen(static::$facade_namespace))];
        return str_replace(['DummyNamespace', 'DummyClass', 'DummyTarget'], $replacements, $stub);
    }
    /**
     * Add an alias to the loader.
     *
     * @param  string  $alias
     * @param  string  $class
     */
    public function alias($alias, $class): void
    {
        $this->aliases[$alias] = $class;
    }
    /**
     * Register the loader on the auto-loader stack.
     */
    public function register(): void
    {
        if (!$this->registered) {
            $this->prepend_to_loader_stack();
            $this->registered = true;
        }
    }
    /**
     * Prepend the load method to the auto-loader stack.
     *
     * @return void
     */
    protected function prepend_to_loader_stack()
    {
        spl_autoload_register($this->load(...), true, true);
    }
    /**
     * Get the registered aliases.
     *
     * @return array
     */
    public function get_aliases()
    {
        return $this->aliases;
    }
    /**
     * Set the registered aliases.
     */
    public function set_aliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }
    /**
     * Indicates if the loader has been registered.
     *
     * @return bool
     */
    public function is_registered()
    {
        return $this->registered;
    }
    /**
     * Set the "registered" state of the loader.
     *
     * @param  bool  $value
     */
    public function set_registered($value): void
    {
        $this->registered = $value;
    }
    /**
     * Set the real-time facade namespace.
     *
     * @param  string  $namespace
     */
    public static function set_facade_namespace($namespace): void
    {
        static::$facade_namespace = rtrim($namespace, '\\') . '\\';
    }
    /**
     * Set the value of the singleton alias loader.
     *
     * @param  \Illuminate\Foundation\AliasLoader  $loader
     */
    public static function set_instance($loader): void
    {
        static::$instance = $loader;
    }
    /**
     * Clone method.
     */
    private function __clone()
    {
    }
}