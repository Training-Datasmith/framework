<?php

declare (strict_types=1);
namespace Illuminate\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command_Loader\Command_Loader_Interface;
use Symfony\Component\Console\Exception\Command_Not_Found_Exception;
class Container_Command_Loader implements Command_Loader_Interface
{
    /**
     * Create a new command loader instance.
     *
     * @param  array<string, \Illuminate\Console\Command|string>  $commandMap
     */
    public function __construct(
        /**
         * The container instance.
         */
        protected \Psr\Container\Container_Interface $container,
        /**
         * A map of command names to classes.
         */
        protected array $command_map
    )
    {
    }
    /**
     * Resolve a command from the container.
     *
     *
     * @throws \Symfony\Component\Console\Exception\CommandNotFoundException
     */
    public function get(string $name): Command
    {
        if (!$this->has($name)) {
            throw new Command_Not_Found_Exception(sprintf('Command "%s" does not exist.', $name));
        }
        return $this->container->get($this->command_map[$name]);
    }
    /**
     * Determines if a command exists.
     */
    public function has(string $name): bool
    {
        return $name && isset($this->command_map[$name]);
    }
    /**
     * Get the command names.
     *
     * @return string[]
     */
    public function get_names(): array
    {
        return array_keys($this->command_map);
    }
}