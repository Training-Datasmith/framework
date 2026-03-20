<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:class')]
class Class_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:class';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Class';
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->option('invokable') ? $this->resolve_stub_path('/stubs/class.invokable.stub') : $this->resolve_stub_path('/stubs/class.stub');
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
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['invokable', 'i', Input_Option::VALUE_NONE, 'Generate a single method, invokable class'], ['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the class already exists']];
    }
}