<?php

declare(strict_types=1);

namespace Illuminate\Foundation;

class AliasLoader
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
    protected static $facadeNamespace = 'Facades\\';

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
    ) {
    }

    /**
     * Get or create the singleton alias loader instance.
     *
     * @return \Illuminate\Foundation\AliasLoader
     */
    public static function getInstance(array $aliases = [])
    {
        if (is_null(static::$instance)) {
            return static::$instance = new static($aliases);
        }

        $aliases = array_merge(static::$instance->getAliases(), $aliases);

        static::$instance->setAliases($aliases);

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
        if (static::$facadeNamespace && str_starts_with($alias, static::$facadeNamespace)) {
            $this->loadFacade($alias);

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
    protected function loadFacade($alias)
    {
        require $this->ensureFacadeExists($alias);
    }

    /**
     * Ensure that the given alias has an existing real-time facade class.
     *
     * @param  string  $alias
     */
    protected function ensureFacadeExists($alias): string
    {
        if (is_file($path = storage_path('framework/cache/facade-'.sha1($alias).'.php'))) {
            return $path;
        }

        $stub = $this->formatFacadeStub(
            $alias,
            file_get_contents(__DIR__.'/stubs/facade.stub')
        );

        // Atomic write to prevent race conditions...
        $tempPath = tempnam(dirname($path), 'facade-');

        // Fix permissions of tempPath because `tempnam()` creates it with permissions set to 0600...
        chmod($tempPath, 0777 - umask());

        file_put_contents($tempPath, $stub);

        rename($tempPath, $path);

        return $path;
    }

    /**
     * Format the facade stub with the proper namespace and class.
     *
     * @param  string  $alias
     * @param  string  $stub
     */
    protected function formatFacadeStub($alias, $stub): string
    {
        $replacements = [
            str_replace('/', '\\', dirname(str_replace('\\', '/', $alias))),
            class_basename($alias),
            substr($alias, strlen(static::$facadeNamespace)),
        ];

        return str_replace(
            ['DummyNamespace', 'DummyClass', 'DummyTarget'],
            $replacements,
            $stub
        );
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
        if (! $this->registered) {
            $this->prependToLoaderStack();

            $this->registered = true;
        }
    }

    /**
     * Prepend the load method to the auto-loader stack.
     *
     * @return void
     */
    protected function prependToLoaderStack()
    {
        spl_autoload_register($this->load(...), true, true);
    }

    /**
     * Get the registered aliases.
     *
     * @return array
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * Set the registered aliases.
     */
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Indicates if the loader has been registered.
     *
     * @return bool
     */
    public function isRegistered()
    {
        return $this->registered;
    }

    /**
     * Set the "registered" state of the loader.
     *
     * @param  bool  $value
     */
    public function setRegistered($value): void
    {
        $this->registered = $value;
    }

    /**
     * Set the real-time facade namespace.
     *
     * @param  string  $namespace
     */
    public static function setFacadeNamespace($namespace): void
    {
        static::$facadeNamespace = rtrim($namespace, '\\').'\\';
    }

    /**
     * Set the value of the singleton alias loader.
     *
     * @param  \Illuminate\Foundation\AliasLoader  $loader
     */
    public static function setInstance($loader): void
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
