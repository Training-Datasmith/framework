<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Two_Column_Detail;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Console\Seeds\Without_Model_Events;
use Illuminate\Support\Arr;
use InvalidArgumentException;
abstract class Seeder
{
    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;
    /**
     * The console command instance.
     *
     * @var \Illuminate\Console\Command
     */
    protected $command;
    /**
     * Seeders that have been called at least one time.
     *
     * @var array
     */
    protected static $called = [];
    /**
     * Run the given seeder class.
     *
     * @param  array|string  $class
     * @param  bool  $silent
     * @return $this
     */
    public function call($class, $silent = false, array $parameters = [])
    {
        $classes = Arr::wrap($class);
        foreach ($classes as $class) {
            $seeder = $this->resolve($class);
            $name = $seeder::class;
            if ($silent === false && isset($this->command)) {
                (new Two_Column_Detail($this->command->get_output()))->render($name, '<fg=yellow;options=bold>RUNNING</>');
            }
            $start_time = microtime(true);
            $seeder->__invoke($parameters);
            if ($silent === false && isset($this->command)) {
                $run_time = number_format((microtime(true) - $start_time) * 1000);
                (new Two_Column_Detail($this->command->get_output()))->render($name, "<fg=gray>{$run_time} ms</> <fg=green;options=bold>DONE</>");
                $this->command->get_output()->writeln('');
            }
            static::$called[] = $class;
        }
        return $this;
    }
    /**
     * Run the given seeder class.
     *
     * @param  array|string  $class
     */
    public function call_with($class, array $parameters = []): void
    {
        $this->call($class, false, $parameters);
    }
    /**
     * Silently run the given seeder class.
     *
     * @param  array|string  $class
     */
    public function call_silent($class, array $parameters = []): void
    {
        $this->call($class, true, $parameters);
    }
    /**
     * Run the given seeder class once.
     *
     * @param  array|string  $class
     * @param  bool  $silent
     */
    public function call_once($class, $silent = false, array $parameters = []): void
    {
        $classes = Arr::wrap($class);
        foreach ($classes as $class) {
            if (in_array($class, static::$called)) {
                continue;
            }
            $this->call($class, $silent, $parameters);
        }
    }
    /**
     * Resolve an instance of the given seeder class.
     *
     * @param  string  $class
     * @return \Illuminate\Database\Seeder
     */
    protected function resolve($class)
    {
        if (isset($this->container)) {
            $instance = $this->container->make($class);
            $instance->set_container($this->container);
        } else {
            $instance = new $class();
        }
        if (isset($this->command)) {
            $instance->set_command($this->command);
        }
        return $instance;
    }
    /**
     * Set the IoC container instance.
     *
     * @return $this
     */
    public function set_container(Container $container)
    {
        $this->container = $container;
        return $this;
    }
    /**
     * Set the console command instance.
     *
     * @return $this
     */
    public function set_command(Command $command)
    {
        $this->command = $command;
        return $this;
    }
    /**
     * Run the database seeds.
     *
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function __invoke(array $parameters = [])
    {
        if (!method_exists($this, 'run')) {
            throw new InvalidArgumentException('Method [run] missing from ' . static::class);
        }
        $callback = fn() => isset($this->container) ? $this->container->call([$this, 'run'], $parameters) : $this->run(...$parameters);
        $uses = array_flip(class_uses_recursive(static::class));
        if (isset($uses[Without_Model_Events::class])) {
            $callback = $this->without_model_events($callback);
        }
        return $callback();
    }
}