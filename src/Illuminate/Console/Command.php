<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Traits\Macroable;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Throwable;
class Command extends Symfony_Command
{
    use Concerns\Calls_Commands;
    use Concerns\Configures_Prompts;
    use Concerns\Has_Parameters;
    use Concerns\Interacts_With_Io;
    use Concerns\Interacts_With_Signals;
    use Concerns\Prompts_For_Missing_Input;
    use Macroable;
    /**
     * The Laravel application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $laravel;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';
    /**
     * The console command help text.
     *
     * @var string
     */
    protected $help = '';
    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = false;
    /**
     * Indicates whether only one instance of the command can run at any given time.
     *
     * @var bool
     */
    protected $isolated = false;
    /**
     * The default exit code for isolated commands.
     *
     * @var self::SUCCESS|self::FAILURE|self::INVALID
     */
    protected $isolated_exit_code = self::SUCCESS;
    /**
     * The console command name aliases.
     *
     * @var string[]
     */
    protected $aliases;
    /**
     * Create a new console command instance.
     */
    public function __construct()
    {
        // We will go ahead and set the name, description, and parameters on console
        // commands just to make things a little easier on the developer. This is
        // so they don't have to all be manually specified in the constructors.
        if (isset($this->signature)) {
            $this->configure_using_fluent_definition();
        } else {
            parent::__construct($this->name);
        }
        // Once we have constructed the command, we'll set the description and other
        // related properties of the command. If a signature wasn't used to build
        // the command we'll set the arguments and the options on this command.
        if (!empty($this->description)) {
            $this->set_description($this->description);
        }
        if (!empty($this->help)) {
            $this->set_help($this->help);
        }
        $this->set_hidden($this->is_hidden());
        if (isset($this->aliases)) {
            $this->set_aliases((array) $this->aliases);
        }
        if (!isset($this->signature)) {
            $this->specify_parameters();
        }
        if ($this instanceof Isolatable) {
            $this->configure_isolation();
        }
    }
    /**
     * Configure the console command using a fluent definition.
     *
     * @return void
     */
    protected function configure_using_fluent_definition()
    {
        [$name, $arguments, $options] = Parser::parse($this->signature);
        parent::__construct($this->name = $name);
        // After parsing the signature we will spin through the arguments and options
        // and set them on this command. These will already be changed into proper
        // instances of these "InputArgument" and "InputOption" Symfony classes.
        $this->get_definition()->add_arguments($arguments);
        $this->get_definition()->add_options($options);
    }
    /**
     * Configure the console command for isolation.
     *
     * @return void
     */
    protected function configure_isolation()
    {
        $this->get_definition()->add_option(new Input_Option('isolated', null, Input_Option::VALUE_OPTIONAL, 'Do not run the command if another instance of the command is already running', $this->isolated));
    }
    /**
     * Run the console command.
     */
    #[\Override]
    public function run(Input_Interface $input, Output_Interface $output): int
    {
        $this->output = $output instanceof Output_Style ? $output : $this->laravel->make(Output_Style::class, ['input' => $input, 'output' => $output]);
        $this->components = $this->laravel->make(Factory::class, ['output' => $this->output]);
        $this->configure_prompts($input);
        try {
            return parent::run($this->input = $input, $this->output);
        } finally {
            $this->untrap();
        }
    }
    /**
     * Execute the console command.
     */
    #[\Override]
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        if ($this instanceof Isolatable && $this->option('isolated') !== false && !$this->command_isolation_mutex()->create($this)) {
            $this->comment(sprintf('The [%s] command is already running.', $this->get_name()));
            return (int) (is_numeric($this->option('isolated')) ? $this->option('isolated') : $this->isolated_exit_code);
        }
        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';
        try {
            return (int) $this->laravel->call([$this, $method]);
        } catch (Manually_Failed_Exception $e) {
            $this->components->error($e->get_message());
            return static::FAILURE;
        } finally {
            if ($this instanceof Isolatable && $this->option('isolated') !== false) {
                $this->command_isolation_mutex()->forget($this);
            }
        }
    }
    /**
     * Get a command isolation mutex instance for the command.
     *
     * @return \Illuminate\Console\CommandMutex
     */
    protected function command_isolation_mutex()
    {
        return $this->laravel->bound(Command_Mutex::class) ? $this->laravel->make(Command_Mutex::class) : $this->laravel->make(Cache_Command_Mutex::class);
    }
    /**
     * Resolve the console command instance for the given command.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    protected function resolve_command($command)
    {
        if (is_string($command)) {
            if (!class_exists($command)) {
                return $this->get_application()->find($command);
            }
            $command = $this->laravel->make($command);
        }
        if ($command instanceof Symfony_Command) {
            $command->set_application($this->get_application());
        }
        if ($command instanceof self) {
            $command->set_laravel($this->get_laravel());
        }
        return $command;
    }
    /**
     * Fail the command manually.
     *
     * @return never
     * @throws \Illuminate\Console\ManuallyFailedException|\Throwable
     */
    public function fail(Throwable|string|null $exception = null): void
    {
        if (is_null($exception)) {
            $exception = 'Command failed manually.';
        }
        if (is_string($exception)) {
            $exception = new Manually_Failed_Exception($exception);
        }
        throw $exception;
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function is_hidden(): bool
    {
        return $this->hidden;
    }
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function set_hidden(bool $hidden = true): static
    {
        parent::set_hidden($this->hidden = $hidden);
        return $this;
    }
    /**
     * Get the Laravel application instance.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    public function get_laravel()
    {
        return $this->laravel;
    }
    /**
     * Set the Laravel application instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $laravel
     */
    public function set_laravel($laravel): void
    {
        $this->laravel = $laravel;
    }
}