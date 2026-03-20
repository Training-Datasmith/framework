<?php

declare (strict_types=1);
namespace Illuminate\Console\Concerns;

use Illuminate\Support\Collection;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Output\Null_Output;
use Symfony\Component\Console\Output\Output_Interface;
trait Calls_Commands
{
    /**
     * Resolve the console command instance for the given command.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    abstract protected function resolve_command($command);
    /**
     * Call another console command.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @return int
     */
    public function call($command, array $arguments = [])
    {
        return $this->run_command($command, $arguments, $this->output);
    }
    /**
     * Call another console command without output.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @return int
     */
    public function call_silent($command, array $arguments = [])
    {
        return $this->run_command($command, $arguments, new Null_Output());
    }
    /**
     * Call another console command without output.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @return int
     */
    public function call_silently($command, array $arguments = [])
    {
        return $this->call_silent($command, $arguments);
    }
    /**
     * Run the given console command.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @return int
     */
    protected function run_command($command, array $arguments, Output_Interface $output)
    {
        $arguments['command'] = $command;
        $result = $this->resolve_command($command)->run($this->create_input_from_arguments($arguments), $output);
        $this->restore_prompts();
        return $result;
    }
    /**
     * Create an input instance from the given arguments.
     *
     * @return \Symfony\Component\Console\Input\ArrayInput
     */
    protected function create_input_from_arguments(array $arguments)
    {
        return tap(new Array_Input(array_merge($this->context(), $arguments)), function ($input): void {
            if ($input->get_parameter_option('--no-interaction')) {
                $input->set_interactive(false);
            }
        });
    }
    /**
     * Get all of the context passed to the command.
     *
     * @return array{'--ansi'?: bool, '--no-ansi'?: bool, '--no-interaction'?: bool, '--quiet'?: bool, '--verbose'?: bool}
     */
    protected function context()
    {
        return (new Collection($this->option()))->only(['ansi', 'no-ansi', 'no-interaction', 'quiet', 'verbose'])->filter()->map_with_keys(fn($value, $key): array => ["--{$key}" => $value])->all();
    }
}