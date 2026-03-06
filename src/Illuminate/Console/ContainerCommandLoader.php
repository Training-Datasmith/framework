<?php

declare(strict_types=1);

namespace Illuminate\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class ContainerCommandLoader implements CommandLoaderInterface
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
        protected \Psr\Container\ContainerInterface $container,
        /**
         * A map of command names to classes.
         */
        protected array $commandMap
    ) {
    }

    /**
     * Resolve a command from the container.
     *
     *
     * @throws \Symfony\Component\Console\Exception\CommandNotFoundException
     */
    public function get(string $name): Command
    {
        if (! $this->has($name)) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        return $this->container->get($this->commandMap[$name]);
    }

    /**
     * Determines if a command exists.
     */
    public function has(string $name): bool
    {
        return $name && isset($this->commandMap[$name]);
    }

    /**
     * Get the command names.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->commandMap);
    }
}
