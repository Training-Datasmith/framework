<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Traits;

trait Can_Configure_Migration_Commands
{
    /**
     * The parameters that should be used when running "migrate:fresh".
     */
    protected function migrate_fresh_using(): array
    {
        $seeder = $this->seeder();
        return array_merge(['--drop-views' => $this->should_drop_views(), '--drop-types' => $this->should_drop_types()], $seeder ? ['--seeder' => $seeder] : ['--seed' => $this->should_seed()]);
    }
    /**
     * Determine if views should be dropped when refreshing the database.
     *
     * @return bool
     */
    protected function should_drop_views()
    {
        return property_exists($this, 'dropViews') ? $this->drop_views : false;
    }
    /**
     * Determine if types should be dropped when refreshing the database.
     *
     * @return bool
     */
    protected function should_drop_types()
    {
        return property_exists($this, 'dropTypes') ? $this->drop_types : false;
    }
    /**
     * Determine if the seed task should be run when refreshing the database.
     *
     * @return bool
     */
    protected function should_seed()
    {
        return property_exists($this, 'seed') ? $this->seed : false;
    }
    /**
     * Determine the specific seeder class that should be used when refreshing the database.
     *
     * @return mixed
     */
    protected function seeder()
    {
        return property_exists($this, 'seeder') ? $this->seeder : false;
    }
}