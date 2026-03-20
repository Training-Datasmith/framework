<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use InvalidArgumentException;
use function Laravel\Prompts\suggest;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:observer')]
class Observer_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:observer';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new observer class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Observer';
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function build_class($name)
    {
        $stub = parent::build_class($name);
        $model = $this->option('model');
        return $model ? $this->replace_model($stub, $model) : $stub;
    }
    /**
     * Replace the model for the given stub.
     *
     * @param  string  $stub
     * @param  string  $model
     */
    protected function replace_model($stub, $model): string
    {
        $model_class = $this->parse_model($model);
        $replace = ['DummyFullModelClass' => $model_class, '{{ namespacedModel }}' => $model_class, '{{namespacedModel}}' => $model_class, 'DummyModelClass' => class_basename($model_class), '{{ model }}' => class_basename($model_class), '{{model}}' => class_basename($model_class), 'DummyModelVariable' => lcfirst(class_basename($model_class)), '{{ modelVariable }}' => lcfirst(class_basename($model_class)), '{{modelVariable}}' => lcfirst(class_basename($model_class))];
        return str_replace(array_keys($replace), array_values($replace), $stub);
    }
    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parse_model($model)
    {
        if (preg_match('/[^A-Za-z0-9_\/\\\\]/', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }
        return $this->qualify_model($model);
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->option('model') ? $this->resolve_stub_path('/stubs/observer.stub') : $this->resolve_stub_path('/stubs/observer.plain.stub');
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
        return $root_namespace . '\Observers';
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the observer already exists'], ['model', 'm', Input_Option::VALUE_OPTIONAL, 'The model that the observer applies to']];
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
        $model = suggest('What model should be observed? (Optional)', $this->find_available_models());
        if ($model) {
            $input->set_option('model', $model);
        }
    }
}