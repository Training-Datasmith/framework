<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Traits\Can_Configure_Migration_Commands;
trait Database_Migrations
{
    use Can_Configure_Migration_Commands;
    /**
     * Define hooks to migrate the database before and after each test.
     */
    public function run_database_migrations(): void
    {
        $this->before_refreshing_database();
        $this->refresh_test_database();
        $this->after_refreshing_database();
        $this->before_application_destroyed(function (): void {
            $this->artisan('migrate:rollback');
            Refresh_Database_State::$migrated = false;
        });
    }
    /**
     * Refresh a conventional test database.
     *
     * @return void
     */
    protected function refresh_test_database()
    {
        $this->artisan('migrate:fresh', $this->migrate_fresh_using());
        $this->app[Kernel::class]->set_artisan(null);
    }
    /**
     * Perform any work that should take place before the database has started refreshing.
     *
     * @return void
     */
    protected function before_refreshing_database()
    {
        // ...
    }
    /**
     * Perform any work that should take place once the database has finished refreshing.
     *
     * @return void
     */
    protected function after_refreshing_database()
    {
        // ...
    }
}