<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Events;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use ReflectionClass;
use Reflection_Exception;
use ReflectionMethod;
use Spl_File_Info;
use Symfony\Component\Finder\Finder;
class Discover_Events
{
    /**
     * The callback to be used to guess class names.
     *
     * @var (callable(SplFileInfo, string): class-string)|null
     */
    public static $guess_class_names_using_callback;
    /**
     * Get all of the events and listeners by searching the given listener directory.
     *
     * @param  array<int, string>|string  $listenerPath
     * @param  string  $basePath
     */
    public static function within($listener_path, $base_path): array
    {
        if (Arr::wrap($listener_path) === []) {
            return [];
        }
        $listeners = new Collection(static::get_listener_events(Finder::create()->files()->in($listener_path), $base_path));
        $discovered_events = [];
        foreach ($listeners as $listener => $events) {
            foreach ($events as $event) {
                if (!isset($discovered_events[$event])) {
                    $discovered_events[$event] = [];
                }
                $discovered_events[$event][] = $listener;
            }
        }
        return $discovered_events;
    }
    /**
     * Get all of the listeners and their corresponding events.
     *
     * @param  iterable<string, SplFileInfo>  $listeners
     * @param  string  $basePath
     */
    protected static function get_listener_events($listeners, $base_path): array
    {
        $listener_events = [];
        foreach ($listeners as $listener) {
            try {
                $listener = new ReflectionClass(static::class_from_file($listener, $base_path));
            } catch (Reflection_Exception) {
                continue;
            }
            if (!$listener->is_instantiable()) {
                continue;
            }
            foreach ($listener->get_methods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (!Str::is('handle*', $method->name) && !Str::is('__invoke', $method->name)) {
                    continue;
                }
                if (!isset($method->get_parameters()[0])) {
                    continue;
                }
                $listener_events[$listener->name . '@' . $method->name] = Reflector::get_parameter_class_names($method->get_parameters()[0]);
            }
        }
        return array_filter($listener_events);
    }
    /**
     * Extract the class name from the given file path.
     *
     * @param  string  $basePath
     * @return class-string
     */
    protected static function class_from_file(Spl_File_Info $file, $base_path): string
    {
        if (static::$guess_class_names_using_callback) {
            return call_user_func(static::$guess_class_names_using_callback, $file, $base_path);
        }
        $class = trim(Str::replace_first($base_path, '', $file->get_real_path()), DIRECTORY_SEPARATOR);
        return ucfirst(Str::camel(str_replace([DIRECTORY_SEPARATOR, ucfirst(basename(app()->path())) . '\\'], ['\\', app()->get_namespace()], ucfirst(Str::replace_last('.php', '', $class)))));
    }
    /**
     * Specify a callback to be used to guess class names.
     *
     * @param  callable(SplFileInfo, string): class-string  $callback
     */
    public static function guess_class_names_using(callable $callback): void
    {
        static::$guess_class_names_using_callback = $callback;
    }
}