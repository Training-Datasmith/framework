<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use function Laravel\Prompts\select;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:enum')]
class Enum_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:enum';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new enum';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Enum';
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        if ($this->option('string') || $this->option('int')) {
            return $this->resolve_stub_path('/stubs/enum.backed.stub');
        }
        return $this->resolve_stub_path('/stubs/enum.stub');
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
            is_dir(app_path('Enums')) => $root_namespace . '\Enums',
            is_dir(app_path('Enumerations')) => $root_namespace . '\Enumerations',
            default => $root_namespace,
        };
    }
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function build_class($name)
    {
        if ($this->option('string') || $this->option('int')) {
            return str_replace(['{{ type }}'], $this->option('string') ? 'string' : 'int', parent::build_class($name));
        }
        return parent::build_class($name);
    }
    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @return void
     */
    protected function after_prompting_for_missing_arguments(Input_Interface $input, Output_Interface $output)
    {
        if ($this->did_receive_options($input)) {
            return;
        }
        $type = select('Which type of enum would you like?', ['pure' => 'Pure enum', 'string' => 'Backed enum (String)', 'int' => 'Backed enum (Integer)']);
        if ($type !== 'pure') {
            $input->set_option($type, true);
        }
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['string', 's', Input_Option::VALUE_NONE, 'Generate a string backed enum.'], ['int', 'i', Input_Option::VALUE_NONE, 'Generate an integer backed enum.'], ['force', 'f', Input_Option::VALUE_NONE, 'Create the enum even if the enum already exists']];
    }
}