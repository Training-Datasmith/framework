<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Factories;

use Illuminate\Console\Generator_Command;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:factory')]
class Factory_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:factory';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model factory';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Factory';
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->resolve_stub_path('/stubs/factory.stub');
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
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function build_class($name): string
    {
        $factory = class_basename(Str::ucfirst(str_replace('Factory', '', $name)));
        $namespace_model = $this->option('model') ? $this->qualify_model($this->option('model')) : $this->qualify_model($this->guess_model_name($name));
        $model = class_basename($namespace_model);
        $namespace = $this->get_namespace(Str::replace_first($this->root_namespace(), 'Database\Factories\\', $this->qualify_class($this->get_name_input())));
        $replace = ['{{ factoryNamespace }}' => $namespace, 'NamespacedDummyModel' => $namespace_model, '{{ namespacedModel }}' => $namespace_model, '{{namespacedModel}}' => $namespace_model, 'DummyModel' => $model, '{{ model }}' => $model, '{{model}}' => $model, '{{ factory }}' => $factory, '{{factory}}' => $factory];
        return str_replace(array_keys($replace), array_values($replace), parent::build_class($name));
    }
    /**
     * Get the destination class path.
     *
     * @param  string  $name
     */
    protected function get_path($name): string
    {
        $name = (new Stringable($name))->replace_first($this->root_namespace(), '')->finish('Factory')->value();
        return $this->laravel->database_path() . '/factories/' . str_replace('\\', '/', $name) . '.php';
    }
    /**
     * Guess the model name from the Factory name or return a default model name.
     *
     * @param  string  $name
     */
    protected function guess_model_name($name): string
    {
        if (str_ends_with($name, 'Factory')) {
            $name = substr($name, 0, -7);
        }
        $model_name = $this->qualify_model(Str::after($name, $this->root_namespace()));
        if (class_exists($model_name)) {
            return $model_name;
        }
        if (is_dir(app_path('Models/'))) {
            return $this->root_namespace() . 'Models\Model';
        }
        return $this->root_namespace() . 'Model';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['model', 'm', Input_Option::VALUE_OPTIONAL, 'The name of the model']];
    }
}