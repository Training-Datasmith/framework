<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Generator_Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:view')]
class View_Make_Command extends Generator_Command
{
    use Creates_Matching_Test;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new view';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:view';
    /**
     * The type of file being generated.
     *
     * @var string
     */
    protected $type = 'View';
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function build_class($name): string
    {
        $contents = parent::build_class($name);
        return str_replace('{{ quote }}', Inspiring::quotes()->random(), $contents);
    }
    /**
     * Get the destination view path.
     *
     * @param  string  $name
     * @return string
     */
    protected function get_path($name)
    {
        return $this->view_path($this->get_name_input() . '.' . $this->option('extension'));
    }
    /**
     * Get the desired view name from the input.
     */
    protected function get_name_input(): string
    {
        $name = trim($this->argument('name'));
        return str_replace(['\\', '.'], '/', $name);
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->resolve_stub_path('/stubs/view.stub');
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
     * Get the destination test case path.
     */
    protected function get_test_path(): string
    {
        return base_path(Str::of($this->test_class_fully_qualified_name())->replace('\\', '/')->replace_first('Tests/Feature', 'tests/Feature')->append('Test.php')->value());
    }
    /**
     * Create the matching test case if requested.
     *
     * @param  string  $path
     */
    protected function handle_test_creation($path): bool
    {
        if (!$this->option('test') && !$this->option('pest') && !$this->option('phpunit')) {
            return false;
        }
        $contents = preg_replace(['/\{{ namespace \}}/', '/\{{ class \}}/', '/\{{ name \}}/'], [$this->test_namespace(), $this->test_class_name(), $this->test_view_name()], File::get($this->get_test_stub()));
        File::ensure_directory_exists(dirname($this->get_test_path()), 0755, true);
        $result = File::put($path = $this->get_test_path(), $contents);
        $this->components->info(sprintf('%s [%s] created successfully.', 'Test', $path));
        return $result !== false;
    }
    /**
     * Get the namespace for the test.
     *
     * @return string
     */
    protected function test_namespace()
    {
        return Str::of($this->test_class_fully_qualified_name())->before_last('\\')->value();
    }
    /**
     * Get the class name for the test.
     *
     * @return string
     */
    protected function test_class_name()
    {
        return Str::of($this->test_class_fully_qualified_name())->after_last('\\')->append('Test')->value();
    }
    /**
     * Get the class fully-qualified name for the test.
     */
    protected function test_class_fully_qualified_name(): string
    {
        $name = Str::of(Str::lower($this->get_name_input()))->replace('.' . $this->option('extension'), '');
        $namespaced_name = Str::of((new Stringable($name))->replace('/', ' ')->explode(' ')->map(fn($part): \Illuminate\Support\Stringable => (new Stringable($part))->ucfirst())->implode('\\'))->replace(['-', '_'], ' ')->explode(' ')->map(fn($part): \Illuminate\Support\Stringable => (new Stringable($part))->ucfirst())->implode('');
        return 'Tests\Feature\View\\' . $namespaced_name;
    }
    /**
     * Get the test stub file for the generator.
     *
     * @return string
     */
    protected function get_test_stub()
    {
        $stub_name = 'view.' . ($this->using_pest() ? 'pest' : 'test') . '.stub';
        return file_exists($custom_path = $this->laravel->base_path("stubs/{$stub_name}")) ? $custom_path : __DIR__ . '/stubs/' . $stub_name;
    }
    /**
     * Get the view name for the test.
     *
     * @return string
     */
    protected function test_view_name()
    {
        return Str::of($this->get_name_input())->replace('/', '.')->lower()->value();
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
    /**
     * Get the console command arguments.
     */
    protected function get_options(): array
    {
        return [['extension', null, Input_Option::VALUE_OPTIONAL, 'The extension of the generated view', 'blade.php'], ['force', 'f', Input_Option::VALUE_NONE, 'Create the view even if the view already exists']];
    }
}