<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Illuminate\Support\Str;
use function Laravel\Prompts\select;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:test')]
class Test_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:test';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new test class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Test';
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        $suffix = $this->option('unit') ? '.unit.stub' : '.stub';
        return $this->using_pest() ? $this->resolve_stub_path('/stubs/pest' . $suffix) : $this->resolve_stub_path('/stubs/test' . $suffix);
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
     * Get the destination class path.
     *
     * @param  string  $name
     */
    protected function get_path($name): string
    {
        $name = Str::replace_first($this->root_namespace(), '', $name);
        return base_path('tests') . str_replace('\\', '/', $name) . '.php';
    }
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function get_default_namespace($root_namespace): string
    {
        if ($this->option('unit')) {
            return $root_namespace . '\Unit';
        }
        return $root_namespace . '\Feature';
    }
    /**
     * Get the root namespace for the class.
     */
    protected function root_namespace(): string
    {
        return 'Tests';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the test even if the test already exists'], ['unit', 'u', Input_Option::VALUE_NONE, 'Create a unit test'], ['pest', null, Input_Option::VALUE_NONE, 'Create a Pest test'], ['phpunit', null, Input_Option::VALUE_NONE, 'Create a PHPUnit test']];
    }
    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @return void
     */
    protected function after_prompting_for_missing_arguments(Input_Interface $input, Output_Interface $output)
    {
        if ($this->is_reserved_name($this->get_name_input()) || $this->did_receive_options($input)) {
            return;
        }
        $type = select('Which type of test would you like?', ['feature' => 'Feature', 'unit' => 'Unit']);
        match ($type) {
            'feature' => null,
            'unit' => $input->set_option('unit', true),
        };
    }
    /**
     * Determine if Pest is being used by the application.
     *
     * @return bool
     */
    protected function using_pest()
    {
        if ($this->option('phpunit')) {
            return false;
        }
        if ($this->option('pest')) {
            return true;
        }
        return function_exists('\Pest\version') && file_exists(base_path('tests') . '/Pest.php');
    }
}