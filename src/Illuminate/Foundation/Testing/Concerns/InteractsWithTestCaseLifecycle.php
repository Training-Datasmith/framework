<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Carbon\Carbon_Immutable;
use Illuminate\Console\Application as Artisan;
use Illuminate\Cookie\Middleware\Encrypt_Cookies;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Bootstrap\Handle_Exceptions;
use Illuminate\Foundation\Bootstrap\Register_Providers;
use Illuminate\Foundation\Console\About_Command;
use Illuminate\Foundation\Http\Middleware\Convert_Empty_Strings_To_Null;
use Illuminate\Foundation\Http\Middleware\Prevent_Requests_During_Maintenance;
use Illuminate\Foundation\Http\Middleware\Trim_Strings;
use Illuminate\Foundation\Http\Middleware\Validate_Csrf_Token;
use Illuminate\Foundation\Testing\Database_Migrations;
use Illuminate\Foundation\Testing\Database_Transactions;
use Illuminate\Foundation\Testing\Database_Truncation;
use Illuminate\Foundation\Testing\Refresh_Database;
use Illuminate\Foundation\Testing\With_Faker;
use Illuminate\Foundation\Testing\Without_Middleware;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Middleware\Handle_Cors;
use Illuminate\Http\Middleware\Trust_Hosts;
use Illuminate\Http\Middleware\Trust_Proxies;
use Illuminate\Http\Resources\Json\Json_Resource;
use Illuminate\Http\Resources\Json_Api\Json_Api_Resource;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\Console\Work_Command;
use Illuminate\Queue\Queue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Encoded_Html_String;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Parallel_Testing;
use Illuminate\Support\Once;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Illuminate\View\Component;
use Mockery;
use Mockery\Exception\Invalid_Count_Exception;
use Php_Unit\Metadata\Annotation\Parser\Registry as PHPUnitRegistry;
use Throwable;
trait Interacts_With_Test_Case_Lifecycle
{
    /**
     * The Illuminate application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;
    /**
     * The callbacks that should be run after the application is created.
     *
     * @var array
     */
    protected $after_application_created_callbacks = [];
    /**
     * The callbacks that should be run before the application is destroyed.
     *
     * @var array
     */
    protected $before_application_destroyed_callbacks = [];
    /**
     * The exception thrown while running an application destruction callback.
     *
     * @var \Throwable
     */
    protected $callback_exception;
    /**
     * Indicates if we have made it through the base setUp function.
     *
     * @var bool
     */
    protected $set_up_has_run = false;
    /**
     * Setup the test environment.
     *
     * @internal
     */
    protected function set_up_the_test_environment(): void
    {
        Facade::clear_resolved_instances();
        if (!$this->app) {
            $this->refresh_application();
            Parallel_Testing::call_set_up_test_case_callbacks($this);
        }
        $this->set_up_traits();
        foreach ($this->after_application_created_callbacks as $callback) {
            $callback();
        }
        Model::set_event_dispatcher($this->app['events']);
        $this->set_up_has_run = true;
    }
    /**
     * Clean up the testing environment before the next test.
     *
     * @internal
     */
    protected function tear_down_the_test_environment(): void
    {
        if ($this->app) {
            $this->call_before_application_destroyed_callbacks();
            Parallel_Testing::call_tear_down_test_case_callbacks($this);
            $this->app->flush();
            $this->app = null;
        }
        $this->set_up_has_run = false;
        if (property_exists($this, 'serverVariables')) {
            $this->server_variables = [];
        }
        if (property_exists($this, 'defaultHeaders')) {
            $this->default_headers = [];
        }
        if (class_exists('Mockery')) {
            if ($container = Mockery::get_container()) {
                $this->add_to_assertion_count($container->mockery_get_expectation_count());
            }
            try {
                Mockery::close();
            } catch (Invalid_Count_Exception $e) {
                if (!Str::contains($e->get_method_name(), ['doWrite', 'askQuestion'])) {
                    throw $e;
                }
            }
        }
        if (class_exists(Carbon::class)) {
            Carbon::set_test_now();
        }
        if (class_exists(Carbon_Immutable::class)) {
            Carbon_Immutable::set_test_now();
        }
        $this->after_application_created_callbacks = [];
        $this->before_application_destroyed_callbacks = [];
        if (property_exists($this, 'originalExceptionHandler')) {
            $this->original_exception_handler = null;
        }
        if (property_exists($this, 'originalDeprecationHandler')) {
            $this->original_deprecation_handler = null;
        }
        About_Command::flush_state();
        Artisan::forget_bootstrappers();
        Component::flush_cache();
        Component::forget_components_resolver();
        Component::forget_factory();
        Convert_Empty_Strings_To_Null::flush_state();
        Factory::flush_state();
        Encoded_Html_String::flush_state();
        Encrypt_Cookies::flush_state();
        Handle_Cors::flush_state();
        Handle_Exceptions::flush_state($this);
        Json_Api_Resource::flush_state();
        Json_Resource::flush_state();
        Markdown::flush_state();
        Migrator::without_migrations([]);
        Once::flush();
        Prevent_Requests_During_Maintenance::flush_state();
        Queue::create_payload_using(null);
        Register_Providers::flush_state();
        Response::flush_state();
        Sleep::fake(false);
        Trim_Strings::flush_state();
        Trust_Proxies::flush_state();
        Trust_Hosts::flush_state();
        Validate_Csrf_Token::flush_state();
        Validator::flush_state();
        Work_Command::flush_state();
        if ($this->callback_exception) {
            throw $this->callback_exception;
        }
    }
    /**
     * Boot the testing helper traits.
     */
    protected function set_up_traits(): array
    {
        $uses = $this->traits_used_by_test ?? array_flip(class_uses_recursive(static::class));
        if (isset($uses[Refresh_Database::class])) {
            $this->refresh_database();
        }
        if (isset($uses[Database_Migrations::class])) {
            $this->run_database_migrations();
        }
        if (isset($uses[Database_Truncation::class])) {
            $this->truncate_database_tables();
        }
        if (isset($uses[Database_Transactions::class])) {
            $this->begin_database_transaction();
        }
        if (isset($uses[Without_Middleware::class])) {
            $this->disable_middleware_for_all_tests();
        }
        if (isset($uses[With_Faker::class])) {
            $this->set_up_faker();
        }
        foreach ($uses as $trait) {
            if (method_exists($this, $method = 'setUp' . class_basename($trait))) {
                $this->{$method}();
            }
            if (method_exists($this, $method = 'tearDown' . class_basename($trait))) {
                $this->before_application_destroyed(fn() => $this->{$method}());
            }
        }
        return $uses;
    }
    /**
     * Clean up the testing environment before the next test case.
     *
     * @internal
     */
    public static function tear_down_after_class_using_test_case(): void
    {
        if (class_exists(Php_Unit_Registry::class)) {
            (function (): void {
                $this->class_doc_blocks = [];
                $this->method_doc_blocks = [];
            })->call(Php_Unit_Registry::get_instance());
        }
    }
    /**
     * Register a callback to be run after the application is created.
     */
    public function after_application_created(callable $callback): void
    {
        $this->after_application_created_callbacks[] = $callback;
        if ($this->set_up_has_run) {
            $callback();
        }
    }
    /**
     * Register a callback to be run before the application is destroyed.
     *
     * @return void
     */
    protected function before_application_destroyed(callable $callback)
    {
        $this->before_application_destroyed_callbacks[] = $callback;
    }
    /**
     * Execute the application's pre-destruction callbacks.
     *
     * @return void
     */
    protected function call_before_application_destroyed_callbacks()
    {
        foreach ($this->before_application_destroyed_callbacks as $callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                if (!$this->callback_exception) {
                    $this->callback_exception = $e;
                }
            }
        }
    }
}