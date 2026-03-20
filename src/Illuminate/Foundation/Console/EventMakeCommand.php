<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:event')]
class Event_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:event';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new event class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Event';
    /**
     * Determine if the class already exists.
     *
     * @param  string  $rawName
     */
    protected function already_exists($raw_name): bool
    {
        return class_exists($raw_name) || $this->files->exists($this->get_path($this->qualify_class($raw_name)));
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->resolve_stub_path('/stubs/event.stub');
    }
    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @return string
     */
    protected function resolve_stub_path(string $stub)
    {
        return file_exists($custom_path = $this->laravel->base_path(trim($stub, '/'))) ? $custom_path : __DIR__ . $stub;
    }
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function get_default_namespace($root_namespace): string
    {
        return $root_namespace . '\Events';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the event already exists']];
    }
}