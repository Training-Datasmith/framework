<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Illuminate\Support\Manager;
class Maintenance_Mode_Manager extends Manager
{
    /**
     * Create an instance of the file based maintenance driver.
     */
    protected function create_file_driver(): File_Based_Maintenance_Mode
    {
        return new File_Based_Maintenance_Mode();
    }
    /**
     * Create an instance of the cache based maintenance driver.
     *
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function create_cache_driver(): Cache_Based_Maintenance_Mode
    {
        return new Cache_Based_Maintenance_Mode($this->container->make('cache'), $this->config->get('app.maintenance.store') ?: $this->config->get('cache.default'), 'illuminate:foundation:down');
    }
    /**
     * Get the default driver name.
     */
    public function get_default_driver(): string
    {
        return $this->config->get('app.maintenance.driver', 'file');
    }
}