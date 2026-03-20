<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Php_Unit\Framework\Test_Case as BaseTestCase;
abstract class Test_Case extends Base_Test_Case
{
    use Concerns\Interacts_With_Container;
    use Concerns\Makes_Http_Requests;
    use Concerns\Interacts_With_Authentication;
    use Concerns\Interacts_With_Console;
    use Concerns\Interacts_With_Database;
    use Concerns\Interacts_With_Deprecation_Handling;
    use Concerns\Interacts_With_Exception_Handling;
    use Concerns\Interacts_With_Session;
    use Concerns\Interacts_With_Time;
    use Concerns\Interacts_With_Test_Case_Lifecycle;
    use Concerns\Interacts_With_Views;
    /**
     * The list of trait that this test uses, fetched recursively.
     *
     * @var array<class-string, int>
     */
    protected array $traits_used_by_test;
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function create_application()
    {
        $app = require Application::infer_base_path() . '/bootstrap/app.php';
        $this->traits_used_by_test = array_flip(class_uses_recursive(static::class));
        if (isset(Cached_State::$cached_config) && isset($this->traits_used_by_test[With_Cached_Config::class])) {
            $this->mark_config_cached($app);
        }
        if (isset(Cached_State::$cached_routes) && isset($this->traits_used_by_test[With_Cached_Routes::class])) {
            $app->booting(fn() => $this->mark_routes_cached($app));
        }
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }
    /**
     * Setup the test environment.
     */
    protected function set_up(): void
    {
        $this->set_up_the_test_environment();
    }
    /**
     * Refresh the application instance.
     *
     * @return void
     */
    protected function refresh_application()
    {
        $this->app = $this->create_application();
    }
    /**
     * Clean up the testing environment before the next test.
     *
     *
     * @throws \Mockery\Exception\InvalidCountException
     */
    protected function tear_down(): void
    {
        $this->tear_down_the_test_environment();
    }
    /**
     * Clean up the testing environment before the next test case.
     */
    public static function tear_down_after_class(): void
    {
        static::tear_down_after_class_using_test_case();
    }
}