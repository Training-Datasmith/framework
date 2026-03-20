<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Generator_Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:model')]
class Model_Make_Command extends Generator_Command
{
    use Creates_Matching_Test;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:model';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Eloquent model class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (parent::handle() === false && !$this->option('force')) {
            if (!$this->already_exists($this->get_name_input())) {
                return false;
            }
            if (!confirm('Do you want to generate additional components for the model?')) {
                return false;
            }
            $this->after_prompting_for_missing_arguments($this->input, $this->output);
        }
        if ($this->option('all')) {
            $this->input->set_option('factory', true);
            $this->input->set_option('seed', true);
            $this->input->set_option('migration', true);
            $this->input->set_option('controller', true);
            $this->input->set_option('policy', true);
            $this->input->set_option('resource', true);
        }
        if ($this->option('factory')) {
            $this->create_factory();
        }
        if ($this->option('migration')) {
            $this->create_migration();
        }
        if ($this->option('seed')) {
            $this->create_seeder();
        }
        if ($this->option('controller') || $this->option('resource') || $this->option('api')) {
            $this->create_controller();
        } elseif ($this->option('requests')) {
            $this->create_form_requests();
        }
        if ($this->option('policy')) {
            $this->create_policy();
        }
    }
    /**
     * Create a model factory for the model.
     *
     * @return void
     */
    protected function create_factory()
    {
        $factory = Str::studly($this->argument('name'));
        $this->call('make:factory', ['name' => "{$factory}Factory", '--model' => $this->qualify_class($this->get_name_input())]);
    }
    /**
     * Create a migration file for the model.
     *
     * @return void
     */
    protected function create_migration()
    {
        $table = Str::snake(Str::plural_studly(class_basename($this->argument('name'))));
        if ($this->option('pivot')) {
            $table = Str::singular($table);
        }
        $this->call('make:migration', ['name' => "create_{$table}_table", '--create' => $table]);
    }
    /**
     * Create a seeder file for the model.
     *
     * @return void
     */
    protected function create_seeder()
    {
        $seeder = Str::studly(class_basename($this->argument('name')));
        $this->call('make:seeder', ['name' => "{$seeder}Seeder"]);
    }
    /**
     * Create a controller for the model.
     *
     * @return void
     */
    protected function create_controller()
    {
        $controller = Str::studly(class_basename($this->argument('name')));
        $model_name = $this->qualify_class($this->get_name_input());
        $this->call('make:controller', array_filter(['name' => "{$controller}Controller", '--model' => $this->option('resource') || $this->option('api') ? $model_name : null, '--api' => $this->option('api'), '--requests' => $this->option('requests') || $this->option('all'), '--test' => $this->option('test'), '--pest' => $this->option('pest')]));
    }
    /**
     * Create the form requests for the model.
     *
     * @return void
     */
    protected function create_form_requests()
    {
        $request = Str::studly(class_basename($this->argument('name')));
        $this->call('make:request', ['name' => "Store{$request}Request"]);
        $this->call('make:request', ['name' => "Update{$request}Request"]);
    }
    /**
     * Create a policy file for the model.
     *
     * @return void
     */
    protected function create_policy()
    {
        $policy = Str::studly(class_basename($this->argument('name')));
        $this->call('make:policy', ['name' => "{$policy}Policy", '--model' => $this->qualify_class($this->get_name_input())]);
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        if ($this->option('pivot')) {
            return $this->resolve_stub_path('/stubs/model.pivot.stub');
        }
        if ($this->option('morph-pivot')) {
            return $this->resolve_stub_path('/stubs/model.morph-pivot.stub');
        }
        return $this->resolve_stub_path('/stubs/model.stub');
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
        return is_dir(app_path('Models')) ? $root_namespace . '\Models' : $root_namespace;
    }
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function build_class($name): string
    {
        $replace = $this->build_factory_replacements();
        return str_replace(array_keys($replace), array_values($replace), parent::build_class($name));
    }
    /**
     * Build the replacements for a factory.
     *
     * @return array<string, string>
     */
    protected function build_factory_replacements(): array
    {
        $replacements = [];
        if ($this->option('factory') || $this->option('all')) {
            $model_path = Str::of($this->argument('name'))->studly()->replace('/', '\\')->to_string();
            $factory_namespace = '\Database\Factories\\' . $model_path . 'Factory';
            $factory_code = <<<EOT
            /** @use HasFactory<{$factory_namespace}> */
                use HasFactory;
            EOT;
            $replacements['{{ factory }}'] = $factory_code;
            $replacements['{{ factoryImport }}'] = 'use Illuminate\Database\Eloquent\Factories\HasFactory;';
        } else {
            $replacements['{{ factory }}'] = '//';
            $replacements["{{ factoryImport }}\n"] = '';
            $replacements["{{ factoryImport }}\r\n"] = '';
        }
        return $replacements;
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['all', 'a', Input_Option::VALUE_NONE, 'Generate a migration, seeder, factory, policy, resource controller, and form request classes for the model'], ['controller', 'c', Input_Option::VALUE_NONE, 'Create a new controller for the model'], ['factory', 'f', Input_Option::VALUE_NONE, 'Create a new factory for the model'], ['force', null, Input_Option::VALUE_NONE, 'Create the class even if the model already exists'], ['migration', 'm', Input_Option::VALUE_NONE, 'Create a new migration file for the model'], ['morph-pivot', null, Input_Option::VALUE_NONE, 'Indicates if the generated model should be a custom polymorphic intermediate table model'], ['policy', null, Input_Option::VALUE_NONE, 'Create a new policy for the model'], ['seed', 's', Input_Option::VALUE_NONE, 'Create a new seeder for the model'], ['pivot', 'p', Input_Option::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model'], ['resource', 'r', Input_Option::VALUE_NONE, 'Indicates if the generated controller should be a resource controller'], ['api', null, Input_Option::VALUE_NONE, 'Indicates if the generated controller should be an API resource controller'], ['requests', 'R', Input_Option::VALUE_NONE, 'Create new form request classes and use them in the resource controller']];
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
        (new Collection(multiselect('Would you like any of the following?', ['seed' => 'Database Seeder', 'factory' => 'Factory', 'requests' => 'Form Requests', 'migration' => 'Migration', 'policy' => 'Policy', 'resource' => 'Resource Controller'])))->each(fn(string $option) => $input->set_option($option, true));
    }
}