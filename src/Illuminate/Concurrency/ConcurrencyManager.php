<?php

declare (strict_types=1);
namespace Illuminate\Concurrency;

use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Multiple_Instance_Manager;
use RuntimeException;
use Spatie\Fork\Fork;
/**
 * @mixin \Illuminate\Contracts\Concurrency\Driver
 */
class Concurrency_Manager extends Multiple_Instance_Manager
{
    /**
     * Get a driver instance by name.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function driver($name = null)
    {
        return $this->instance($name);
    }
    /**
     * Create an instance of the process concurrency driver.
     */
    public function create_process_driver(): \Illuminate\Concurrency\Process_Driver
    {
        return new Process_Driver($this->app->make(Process_Factory::class));
    }
    /**
     * Create an instance of the fork concurrency driver.
     *
     *
     * @throws \RuntimeException
     */
    public function create_fork_driver(): \Illuminate\Concurrency\Fork_Driver
    {
        if (!$this->app->running_in_console()) {
            throw new RuntimeException('Due to PHP limitations, the fork driver may not be used within web requests.');
        }
        if (!class_exists(Fork::class)) {
            throw new RuntimeException('Please install the "spatie/fork" Composer package in order to utilize the "fork" driver.');
        }
        return new Fork_Driver();
    }
    /**
     * Create an instance of the sync concurrency driver.
     */
    public function create_sync_driver(): \Illuminate\Concurrency\Sync_Driver
    {
        return new Sync_Driver();
    }
    /**
     * Get the default instance name.
     *
     * @return string
     */
    public function get_default_instance()
    {
        return $this->app['config']['concurrency.default'] ?? $this->app['config']['concurrency.driver'] ?? 'process';
    }
    /**
     * Set the default instance name.
     *
     * @param  string  $name
     */
    public function set_default_instance($name): void
    {
        $this->app['config']['concurrency.default'] = $name;
        $this->app['config']['concurrency.driver'] = $name;
    }
    /**
     * Get the instance specific configuration.
     *
     * @param  string  $name
     * @return array
     */
    public function get_instance_config($name)
    {
        return $this->app['config']->get('concurrency.driver.' . $name, ['driver' => $name]);
    }
}