<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Concerns\Finds_Available_Models;
use Illuminate\Contracts\Console\Prompts_For_Missing_Input;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Finder\Finder;
abstract class Generator_Command extends Command implements Prompts_For_Missing_Input
{
    use Finds_Available_Models;
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type;
    /**
     * Reserved names that cannot be used for generation.
     *
     * @var string[]
     */
    protected $reserved_names = ['__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require', 'require_once', 'return', 'self', 'static', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield', '__CLASS__', '__DIR__', '__FILE__', '__FUNCTION__', '__LINE__', '__METHOD__', '__NAMESPACE__', '__TRAIT__'];
    /**
     * Create a new generator command instance.
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files
    )
    {
        parent::__construct();
        if (in_array(Creates_Matching_Test::class, class_uses_recursive($this))) {
            $this->add_test_options();
        }
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    abstract protected function get_stub();
    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        // First we need to ensure that the given name is not a reserved word within the PHP
        // language and that the class name will actually be valid. If it is not valid we
        // can error now and prevent from polluting the filesystem using invalid files.
        if ($this->is_reserved_name($this->get_name_input())) {
            $this->components->error('The name "' . $this->get_name_input() . '" is reserved by PHP.');
            return false;
        }
        $name = $this->qualify_class($this->get_name_input());
        $path = $this->get_path($name);
        // Next, We will check to see if the class already exists. If it does, we don't want
        // to create the class and overwrite the user's code. So, we will bail out so the
        // code is untouched. Otherwise, we will continue generating this class' files.
        if ((!$this->has_option('force') || !$this->option('force')) && $this->already_exists($this->get_name_input())) {
            $this->components->error($this->type . ' already exists.');
            return false;
        }
        // Next, we will generate the path to the location where this class' file should get
        // written. Then, we will build the class and make the proper replacements on the
        // stub files so that it gets the correctly formatted namespace and class name.
        $this->make_directory($path);
        $this->files->put($path, $this->sort_imports($this->build_class($name)));
        $info = $this->type;
        if (in_array(Creates_Matching_Test::class, class_uses_recursive($this))) {
            $this->handle_test_creation($path);
        }
        if (windows_os()) {
            $path = str_replace('/', '\\', $path);
        }
        $this->components->info(sprintf('%s [%s] created successfully.', $info, $path));
    }
    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function qualify_class($name)
    {
        $name = ltrim($name, '\/');
        $name = str_replace('/', '\\', $name);
        $root_namespace = $this->root_namespace();
        if (Str::starts_with($name, $root_namespace)) {
            return $name;
        }
        return $this->qualify_class($this->get_default_namespace(trim($root_namespace, '\\')) . '\\' . $name);
    }
    /**
     * Qualify the given model class base name.
     *
     * @return class-string
     */
    protected function qualify_model(string $model)
    {
        $model = ltrim($model, '\/');
        $model = str_replace('/', '\\', $model);
        $root_namespace = $this->root_namespace();
        if (Str::starts_with($model, $root_namespace)) {
            return $model;
        }
        return is_dir(app_path('Models')) ? $root_namespace . 'Models\\' . $model : $root_namespace . $model;
    }
    /**
     * Get a list of possible model names.
     *
     * @return array<int, string>
     *
     * @deprecated 12.38.0 Use `findAvailableModels()` method instead.
     */
    protected function possible_models()
    {
        return $this->find_available_models();
    }
    /**
     * Get a list of possible event names.
     *
     * @return array<int, string>
     */
    protected function possible_events()
    {
        $event_path = app_path('Events');
        if (!is_dir($event_path)) {
            return [];
        }
        return (new Collection(Finder::create()->files()->depth(0)->in($event_path)))->map(fn($file) => $file->get_basename('.php'))->sort()->values()->all();
    }
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function get_default_namespace($root_namespace)
    {
        return $root_namespace;
    }
    /**
     * Determine if the class already exists.
     *
     * @param  string  $rawName
     * @return bool
     */
    protected function already_exists($raw_name)
    {
        return $this->files->exists($this->get_path($this->qualify_class($raw_name)));
    }
    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function get_path($name)
    {
        $name = Str::replace_first($this->root_namespace(), '', $name);
        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '.php';
    }
    /**
     * Build the directory for the class if necessary.
     *
     * @param  string  $path
     * @return string
     */
    protected function make_directory($path)
    {
        if (!$this->files->is_directory(dirname($path))) {
            $this->files->make_directory(dirname($path), 0777, true, true);
        }
        return $path;
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
        $stub = $this->files->get($this->get_stub());
        return $this->replace_namespace($stub, $name)->replace_class($stub, $name);
    }
    /**
     * Replace the namespace for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replace_namespace(&$stub, $name)
    {
        $searches = [['DummyNamespace', 'DummyRootNamespace', 'NamespacedDummyUserModel'], ['{{ namespace }}', '{{ rootNamespace }}', '{{ namespacedUserModel }}'], ['{{namespace}}', '{{rootNamespace}}', '{{namespacedUserModel}}']];
        foreach ($searches as $search) {
            $stub = str_replace($search, [$this->get_namespace($name), $this->root_namespace(), $this->user_provider_model()], $stub);
        }
        return $this;
    }
    /**
     * Get the full namespace for a given class, without the class name.
     *
     * @param  string  $name
     * @return string
     */
    protected function get_namespace($name)
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }
    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replace_class($stub, $name)
    {
        $class = str_replace($this->get_namespace($name) . '\\', '', $name);
        return str_replace(['DummyClass', '{{ class }}', '{{class}}'], $class, $stub);
    }
    /**
     * Alphabetically sorts the imports for the given stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function sort_imports($stub)
    {
        if (preg_match('/(?P<imports>(?:^use [^;{]+;$\n?)+)/m', $stub, $match)) {
            $imports = explode("\n", trim($match['imports']));
            sort($imports);
            return str_replace(trim($match['imports']), implode("\n", $imports), $stub);
        }
        return $stub;
    }
    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function get_name_input()
    {
        $name = trim($this->argument('name'));
        if (Str::ends_with($name, '.php')) {
            return Str::substr($name, 0, -4);
        }
        return $name;
    }
    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function root_namespace()
    {
        return $this->laravel->get_namespace();
    }
    /**
     * Get the model for the default guard's user provider.
     *
     * @return string|null
     */
    protected function user_provider_model()
    {
        $config = $this->laravel['config'];
        $provider = $config->get('auth.guards.' . $config->get('auth.defaults.guard') . '.provider');
        return $config->get("auth.providers.{$provider}.model");
    }
    /**
     * Checks whether the given name is reserved.
     *
     * @param  string  $name
     * @return bool
     */
    protected function is_reserved_name($name)
    {
        return in_array(strtolower($name), (new Collection($this->reserved_names))->transform(fn($name) => strtolower($name))->all());
    }
    /**
     * Get the first view directory path from the application configuration.
     *
     * @param  string  $path
     * @return string
     */
    protected function view_path(?string $path = '')
    {
        $views = $this->laravel['config']['view.paths'][0] ?? resource_path('views');
        return $views . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
    /**
     * Get the console command arguments.
     *
     * @return (InputArgument|array{
     *    0: non-empty-string,
     *    1?: InputArgument::REQUIRED|InputArgument::OPTIONAL|InputArgument::IS_ARRAY,
     *    2?: string,
     *    3?: mixed,
     *    4?: list<string|Suggestion>|\Closure(CompletionInput, CompletionSuggestions): list<string|Suggestion>
     * })[]
     */
    protected function get_arguments(): array
    {
        return [['name', Input_Argument::REQUIRED, 'The name of the ' . strtolower($this->type)]];
    }
    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, string|array{string, string}|\Closure(): (array<int, string>|string|int|bool)>
     */
    protected function prompt_for_missing_arguments_using(): array
    {
        return ['name' => ['What should the ' . strtolower($this->type) . ' be named?', match ($this->type) {
            'Cast' => 'E.g. Json',
            'Channel' => 'E.g. OrderChannel',
            'Console command' => 'E.g. SendEmails',
            'Component' => 'E.g. Alert',
            'Controller' => 'E.g. UserController',
            'Event' => 'E.g. PodcastProcessed',
            'Exception' => 'E.g. InvalidOrderException',
            'Factory' => 'E.g. PostFactory',
            'Job' => 'E.g. ProcessPodcast',
            'Listener' => 'E.g. SendPodcastNotification',
            'Mailable' => 'E.g. OrderShipped',
            'Middleware' => 'E.g. EnsureTokenIsValid',
            'Model' => 'E.g. Flight',
            'Notification' => 'E.g. InvoicePaid',
            'Observer' => 'E.g. UserObserver',
            'Policy' => 'E.g. PostPolicy',
            'Provider' => 'E.g. ElasticServiceProvider',
            'Request' => 'E.g. StorePodcastRequest',
            'Resource' => 'E.g. UserResource',
            'Rule' => 'E.g. Uppercase',
            'Scope' => 'E.g. TrendingScope',
            'Seeder' => 'E.g. UserSeeder',
            'Test' => 'E.g. UserTest',
            default => '',
        }]];
    }
}