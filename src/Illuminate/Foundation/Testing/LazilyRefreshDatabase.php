<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

trait Lazily_Refresh_Database
{
    use Refresh_Database {
        refreshDatabase as baseRefreshDatabase;
    }
    /**
     * Define hooks to migrate the database before and after each test.
     */
    public function refresh_database(): void
    {
        $database = $this->app->make('db');
        $callback = function (): void {
            if (Refresh_Database_State::$lazily_refreshed) {
                return;
            }
            Refresh_Database_State::$lazily_refreshed = true;
            if (property_exists($this, 'mockConsoleOutput')) {
                $should_mock_output = $this->mock_console_output;
                $this->mock_console_output = false;
            }
            $this->base_refresh_database();
            if (property_exists($this, 'mockConsoleOutput')) {
                $this->mock_console_output = $should_mock_output;
            }
        };
        $database->before_starting_transaction($callback);
        $database->before_executing($callback);
        $this->before_application_destroyed(function (): void {
            Refresh_Database_State::$lazily_refreshed = false;
        });
    }
}