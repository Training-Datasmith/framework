<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Closure;
use Illuminate\Console\Events\Artisan_Starting;
use Illuminate\Contracts\Console\Application as ApplicationContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use function Illuminate\Support\artisan_binary;
use function Illuminate\Support\php_binary;
use Illuminate\Support\Process_Utils;
use ReflectionClass;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Exception\Command_Not_Found_Exception;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Input\String_Input;
use Symfony\Component\Console\Output\Buffered_Output;
class Application extends Symfony_Application implements Application_Contract
{
    /**
     * The output from the previous command.
     *
     * @var \Symfony\Component\Console\Output\BufferedOutput
     */
    protected $last_output;
    /**
     * The console application bootstrappers.
     *
     * @var array<array-key, \Closure($this): void>
     */
    protected static $bootstrappers = [];
    /**
     * A map of command names to classes.
     *
     * @var array<string, \Illuminate\Console\Command|string>
     */
    protected $command_map = [];
    /**
     * Create a new Artisan console application.
     */
    public function __construct(
        /**
         * The Laravel application instance.
         */
        protected \Illuminate\Contracts\Container\Container $laravel,
        /**
         * The event dispatcher instance.
         */
        protected \Illuminate\Contracts\Events\Dispatcher $events,
        string $version
    )
    {
        parent::__construct('Laravel Framework', $version);
        $this->set_auto_exit(false);
        $this->set_catch_exceptions(false);
        $this->events->dispatch(new Artisan_Starting($this));
        $this->bootstrap();
    }
    /**
     * Determine the proper PHP executable.
     *
     * @return string
     */
    public static function php_binary()
    {
        return Process_Utils::escape_argument(php_binary());
    }
    /**
     * Determine the proper Artisan executable.
     *
     * @return string
     */
    public static function artisan_binary()
    {
        return Process_Utils::escape_argument(artisan_binary());
    }
    /**
     * Format the given command as a fully-qualified executable command.
     *
     * @param  string  $string
     */
    public static function format_command_string($string): string
    {
        return sprintf('%s %s %s', static::php_binary(), static::artisan_binary(), $string);
    }
    /**
     * Register a console "starting" bootstrapper.
     *
     * @param  \Closure($this): void  $callback
     */
    public static function starting(Closure $callback): void
    {
        static::$bootstrappers[] = $callback;
    }
    /**
     * Bootstrap the console application.
     *
     * @return void
     */
    protected function bootstrap()
    {
        foreach (static::$bootstrappers as $bootstrapper) {
            $bootstrapper($this);
        }
    }
    /**
     * Clear the console application bootstrappers.
     */
    public static function forget_bootstrappers(): void
    {
        static::$bootstrappers = [];
    }
    /**
     * Run an Artisan console command by name.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $outputBuffer
     *
     * @throws \Symfony\Component\Console\Exception\CommandNotFoundException
     */
    public function call($command, array $parameters = [], $output_buffer = null): int
    {
        [$command, $input] = $this->parse_command($command, $parameters);
        if (!$this->has($command)) {
            throw new Command_Not_Found_Exception(sprintf('The command "%s" does not exist.', $command));
        }
        return $this->run($input, $this->last_output = $output_buffer ?: new Buffered_Output());
    }
    /**
     * Parse the incoming Artisan command and its input.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @param  array  $parameters
     * @return array<string, \Symfony\Component\Console\Input\ArrayInput>
     */
    protected function parse_command($command, $parameters): array
    {
        if (is_subclass_of($command, Symfony_Command::class)) {
            $calling_class = true;
            if (is_object($command)) {
                $command = $command::class;
            }
            $command = $this->laravel->make($command)->get_name();
        }
        if (!isset($calling_class) && empty($parameters)) {
            $command = $this->get_command_name($input = new String_Input($command));
        } else {
            array_unshift($parameters, $command);
            $input = new Array_Input($parameters);
        }
        return [$command, $input];
    }
    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        return $this->last_output && method_exists($this->last_output, 'fetch') ? $this->last_output->fetch() : '';
    }
    /**
     * Add an array of commands to the console.
     *
     * @param  array<int, \Symfony\Component\Console\Command\Command>  $commands
     */
    #[\Override]
    public function add_commands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->add_command($command);
        }
    }
    /**
     * Add a command to the console.
     */
    #[\Override]
    public function add(Symfony_Command $command): ?Symfony_Command
    {
        return $this->add_command($command);
    }
    /**
     * Add a command to the console.
     */
    public function add_command(Symfony_Command|callable $command): ?Symfony_Command
    {
        if ($command instanceof Command) {
            $command->set_laravel($this->laravel);
        }
        return $this->add_to_parent($command);
    }
    /**
     * Add the command to the parent instance.
     *
     * @return \Symfony\Component\Console\Command\Command
     */
    protected function add_to_parent(Symfony_Command $command)
    {
        if (method_exists(Symfony_Application::class, 'addCommand')) {
            return parent::add_command($command);
        }
        return parent::add($command);
    }
    /**
     * Add a command, resolving through the application.
     *
     * @param  \Illuminate\Console\Command|string  $command
     */
    public function resolve($command): ?\Symfony\Component\Console\Command\Command
    {
        if (is_subclass_of($command, Symfony_Command::class)) {
            $attribute = (new ReflectionClass($command))->get_attributes(As_Command::class);
            $command_name = !empty($attribute) ? $attribute[0]->new_instance()->name : null;
            if (!is_null($command_name)) {
                foreach (explode('|', $command_name) as $name) {
                    $this->command_map[$name] = $command;
                }
                return null;
            }
        }
        if ($command instanceof Command) {
            return $this->add($command);
        }
        return $this->add($this->laravel->make($command));
    }
    /**
     * Resolve an array of commands through the application.
     *
     * @param  mixed  $commands
     * @return $this
     */
    public function resolve_commands($commands): static
    {
        $commands = is_array($commands) ? $commands : func_get_args();
        foreach ($commands as $command) {
            $this->resolve($command);
        }
        return $this;
    }
    /**
     * Set the container command loader for lazy resolution.
     *
     * @return $this
     */
    public function set_container_command_loader(): static
    {
        $this->set_command_loader(new Container_Command_Loader($this->laravel, $this->command_map));
        return $this;
    }
    /**
     * Get the default input definition for the application.
     *
     * This is used to add the --env option to every available command.
     */
    #[\Override]
    protected function get_default_input_definition(): Input_Definition
    {
        return tap(parent::get_default_input_definition(), function ($definition): void {
            $definition->add_option($this->get_environment_option());
        });
    }
    /**
     * Get the global environment option for the definition.
     */
    protected function get_environment_option(): \Symfony\Component\Console\Input\Input_Option
    {
        $message = 'The environment the command should run under';
        return new Input_Option('--env', null, Input_Option::VALUE_OPTIONAL, $message);
    }
    /**
     * Get the Laravel application instance.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    public function get_laravel(): \Illuminate\Contracts\Container\Container
    {
        return $this->laravel;
    }
}