<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Concerns\Creates_Matching_Test;
use Illuminate\Console\Generator_Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use function Laravel\Prompts\select;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'make:mail')]
class Mail_Make_Command extends Generator_Command
{
    use Creates_Matching_Test;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:mail';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new email class';
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Mailable';
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (parent::handle() === false && !$this->option('force')) {
            return;
        }
        if ($this->option('markdown') !== false) {
            $this->write_markdown_template();
        }
        if ($this->option('view') !== false) {
            $this->write_view();
        }
    }
    /**
     * Write the Markdown template for the mailable.
     *
     * @return void
     */
    protected function write_markdown_template()
    {
        $separator = '/';
        if (windows_os()) {
            $separator = '\\';
        }
        $path = $this->view_path(str_replace('.', $separator, $this->get_view()) . '.blade.php');
        if ($this->files->exists($path)) {
            return $this->components->error(sprintf('%s [%s] already exists.', 'Markdown view', $path));
        }
        $this->files->ensure_directory_exists(dirname($path));
        $this->files->put($path, file_get_contents(__DIR__ . '/stubs/markdown.stub'));
        $this->components->info(sprintf('%s [%s] created successfully.', 'Markdown view', $path));
    }
    /**
     * Write the Blade template for the mailable.
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
        if ($this->files->exists($path)) {
            return $this->components->error(sprintf('%s [%s] already exists.', 'View', $path));
        }
        $this->files->ensure_directory_exists(dirname($path));
        $stub = str_replace('{{ quote }}', Inspiring::quotes()->random(), file_get_contents(__DIR__ . '/stubs/view.stub'));
        $this->files->put($path, $stub);
        $this->components->info(sprintf('%s [%s] created successfully.', 'View', $path));
    }
    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function build_class($name): string|array
    {
        $class = str_replace('{{ subject }}', Str::headline(str_replace($this->get_namespace($name) . '\\', '', $name)), parent::build_class($name));
        if ($this->option('markdown') !== false || $this->option('view') !== false) {
            return str_replace(['DummyView', '{{ view }}'], $this->get_view(), $class);
        }
        return $class;
    }
    /**
     * Get the view name.
     *
     * @return string
     */
    protected function get_view()
    {
        $view = $this->option('markdown') ?: $this->option('view');
        if (!$view) {
            $name = str_replace('\\', '/', $this->argument('name'));
            $view = 'mail.' . (new Collection(explode('/', $name)))->map(fn($part) => Str::kebab($part))->implode('.');
        }
        return $view;
    }
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function get_stub()
    {
        if ($this->option('markdown') !== false) {
            return $this->resolve_stub_path('/stubs/markdown-mail.stub');
        }
        if ($this->option('view') !== false) {
            return $this->resolve_stub_path('/stubs/view-mail.stub');
        }
        return $this->resolve_stub_path('/stubs/mail.stub');
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
        return $root_namespace . '\Mail';
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['force', 'f', Input_Option::VALUE_NONE, 'Create the class even if the mailable already exists'], ['markdown', 'm', Input_Option::VALUE_OPTIONAL, 'Create a new Markdown template for the mailable', false], ['view', null, Input_Option::VALUE_OPTIONAL, 'Create a new Blade template for the mailable', false]];
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
        $type = select('Would you like to create a view?', ['markdown' => 'Markdown View', 'view' => 'Empty View', 'none' => 'No View']);
        if ($type !== 'none') {
            $input->set_option($type, null);
        }
    }
}