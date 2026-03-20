<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:trait')]
class Trait_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:trait';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new trait';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Trait';
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->resolve_stub_path('/stubs/trait.stub');
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
     * @return string
     */
    protected function get_default_namespace($root_namespace)
    {
        return match (true) {
            is_dir(app_path('Concerns')) => $root_namespace . '\Concerns',
            is_dir(app_path('Traits')) => $root_namespace . '\Traits',
            default => $root_namespace,
        };
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the trait even if the trait already exists']];
    }
}