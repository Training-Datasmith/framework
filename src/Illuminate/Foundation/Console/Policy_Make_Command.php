<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Generator_Command;
use Illuminate\Support\Str;
use function Laravel\Prompts\suggest;
use LogicException;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:policy')]
class Policy_Make_Command extends Generator_Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:policy';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new policy class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Policy';
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function build_class($name)
    {
        $stub = $this->replace_user_namespace(parent::build_class($name));
        $model = $this->option('model');
        return $model ? $this->replace_model($stub, $model) : $stub;
    }
    /**
     * Replace the User model namespace.
     *
     * @param  string  $stub
     * @return string
     */
    protected function replace_user_namespace($stub)
    {
        $model = $this->user_provider_model();
        if (!$model) {
            return $stub;
        }
        return str_replace($this->root_namespace() . 'User', $model, $stub);
    }
    /**
     * Get the model for the guard's user provider.
     *
     * @return string|null
     *
     * @throws \LogicException
     */
    protected function user_provider_model()
    {
        $config = $this->laravel['config'];
        $guard = $this->option('guard') ?: $config->get('auth.defaults.guard');
        if (is_null($guard_provider = $config->get('auth.guards.' . $guard . '.provider'))) {
            throw new LogicException('The [' . $guard . '] guard is not defined in your "auth" configuration file.');
        }
        if (!$config->get('auth.providers.' . $guard_provider . '.model')) {
            return 'App\Models\User';
        }
        return $config->get('auth.providers.' . $guard_provider . '.model');
    }
    /**
     * Replace the model for the given stub.
     *
     * @param  string  $stub
     * @param  string  $model
     * @return string
     */
    protected function replace_model($stub, $model): ?string
    {
        $model = str_replace('/', '\\', $model);
        if (str_starts_with($model, '\\')) {
            $namespaced_model = trim($model, '\\');
        } else {
            $namespaced_model = $this->qualify_model($model);
        }
        $model = class_basename(trim($model, '\\'));
        $dummy_user = class_basename($this->user_provider_model());
        $dummy_model = Str::camel($model) === 'user' ? 'model' : $model;
        $replace = ['NamespacedDummyModel' => $namespaced_model, '{{ namespacedModel }}' => $namespaced_model, '{{namespacedModel}}' => $namespaced_model, 'DummyModel' => $model, '{{ model }}' => $model, '{{model}}' => $model, 'dummyModel' => Str::camel($dummy_model), '{{ modelVariable }}' => Str::camel($dummy_model), '{{modelVariable}}' => Str::camel($dummy_model), 'DummyUser' => $dummy_user, '{{ user }}' => $dummy_user, '{{user}}' => $dummy_user, '$user' => '$' . Str::camel($dummy_user)];
        $stub = str_replace(array_keys($replace), array_values($replace), $stub);
        return preg_replace(vsprintf('/use %s;[\r\n]+use %s;/', [preg_quote($namespaced_model, '/'), preg_quote($namespaced_model, '/')]), "use {$namespaced_model};", $stub);
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->option('model') ? $this->resolve_stub_path('/stubs/policy.stub') : $this->resolve_stub_path('/stubs/policy.plain.stub');
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
        return $root_namespace . '\Policies';
    }
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the policy already exists'], ['model', 'm', Input_Option::VALUE_OPTIONAL, 'The model that the policy applies to'], ['guard', 'g', Input_Option::VALUE_OPTIONAL, 'The guard that the policy relies on']];
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
        $model = suggest('What model should this policy apply to? (Optional)', $this->find_available_models());
        if ($model) {
            $input->set_option('model', $model);
        }
    }
}