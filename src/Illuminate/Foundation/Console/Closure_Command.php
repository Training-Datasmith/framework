<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Manually_Failed_Exception;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Traits\Forwards_Calls;
use ReflectionFunction;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @mixin \Illuminate\Console\Scheduling\Event
 */
class Closure_Command extends Command
{
    use Forwards_Calls;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';
    /**
     * Create a new command instance.
     *
     * @param  string  $signature
     */
    public function __construct(
        $signature,
        /**
         * The command callback.
         */
        protected \Closure $callback
    )
    {
        $this->signature = $signature;
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $inputs = array_merge($input->get_arguments(), $input->get_options());
        $parameters = [];
        foreach ((new ReflectionFunction($this->callback))->get_parameters() as $parameter) {
            if (isset($inputs[$parameter->get_name()])) {
                $parameters[$parameter->get_name()] = $inputs[$parameter->get_name()];
            }
        }
        try {
            return (int) $this->laravel->call($this->callback->bind_to($this, $this), $parameters);
        } catch (Manually_Failed_Exception $e) {
            $this->components->error($e->get_message());
            return static::FAILURE;
        }
    }
    /**
     * Set the description for the command.
     *
     * @return $this
     */
    public function purpose(string $description): static
    {
        return $this->describe($description);
    }
    /**
     * Set the description for the command.
     *
     * @return $this
     */
    public function describe(string $description): static
    {
        $this->set_description($description);
        return $this;
    }
    /**
     * Create a new scheduled event for the command.
     *
     * @param  array  $parameters
     * @return \Illuminate\Console\Scheduling\Event
     */
    public function schedule($parameters = [])
    {
        return Schedule::command($this->name, $parameters);
    }
    /**
     * Dynamically proxy calls to a new scheduled event.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        return $this->forward_call_to($this->schedule(), $method, $parameters);
    }
}