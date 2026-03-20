<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Generator_Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'make:component')]
class Component_Make_Command extends Generator_Command
{
    use Creates_Matching_Test;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:component';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new view component class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Component';
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->option('view')) {
            return $this->write_view();
        }
        if (parent::handle() === false && !$this->option('force')) {
            return;
        }
        if (!$this->option('inline')) {
            $this->write_view();
        }
    }
    /**
     * Write the view for the component.
     *
     * @return void
     */
    protected function write_view()
    {
        $separator = '/';
        if (windows_os()) {
            $separator = '\\';
        }
        $path = $this->view_path(str_replace('.', $separator, $this->get_view()) . '.blade.php');
        if (!$this->files->is_directory(dirname($path))) {
            $this->files->make_directory(dirname($path), 0777, true, true);
        }
        if ($this->files->exists($path) && !$this->option('force')) {
            $this->components->error('View already exists.');
            return;
        }
        file_put_contents($path, '<div>
    <!-- ' . Inspiring::quotes()->random() . ' -->
</div>');
        $this->components->info(sprintf('%s [%s] created successfully.', 'View', $path));
    }
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     */
    protected function build_class($name): string
    {
        if ($this->option('inline')) {
            return str_replace(['DummyView', '{{ view }}'], "<<<'blade'\n<div>\n    <!-- " . Inspiring::quotes()->random() . " -->\n</div>\nblade", parent::build_class($name));
        }
        return str_replace(['DummyView', '{{ view }}'], 'view(\'' . $this->get_view() . '\')', parent::build_class($name));
    }
    /**
     * Get the view name relative to the view path.
     *
     * @return string view
     */
    protected function get_view(): string
    {
        $segments = explode('/', str_replace('\\', '/', $this->argument('name')));
        $name = array_pop($segments);
        $path = is_string($this->option('path')) ? explode('/', trim($this->option('path'), '/')) : ['components', ...$segments];
        $path[] = $name;
        return (new Collection($path))->map(fn($segment) => Str::kebab($segment))->implode('.');
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        return $this->resolve_stub_path('/stubs/view-component.stub');
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
        return $root_namespace . '\View\Components';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['inline', null, Input_Option::VALUE_NONE, 'Create a component that renders an inline view'], ['view', null, Input_Option::VALUE_NONE, 'Create an anonymous component with only a view'], ['path', null, Input_Option::VALUE_REQUIRED, 'The location where the component view should be created'], ['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the component already exists']];
    }
}