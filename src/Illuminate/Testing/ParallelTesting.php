<?php

declare(strict_types=1);

namespace Illuminate\Testing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

class ParallelTesting
{
    /**
     * The options resolver callback.
     *
     * @var \Closure|null
     */
    protected $optionsResolver;

    /**
     * The token resolver callback.
     *
     * @var \Closure|null
     */
    protected $tokenResolver;

    /**
     * All of the registered "setUp" process callbacks.
     *
     * @var array
     */
    protected $setUpProcessCallbacks = [];

    /**
     * All of the registered "setUp" test case callbacks.
     *
     * @var array
     */
    protected $setUpTestCaseCallbacks = [];

    /**
     * All of the registered "setUp" test database callbacks prior to the migrations.
     *
     * @var array
     */
    protected $setUpTestDatabaseBeforeMigratingCallbacks = [];

    /**
     * All of the registered "setUp" test database callbacks.
     *
     * @var array
     */
    protected $setUpTestDatabaseCallbacks = [];

    /**
     * All of the registered "tearDown" process callbacks.
     *
     * @var array
     */
    protected $tearDownProcessCallbacks = [];

    /**
     * All of the registered "tearDown" test case callbacks.
     *
     * @var array
     */
    protected $tearDownTestCaseCallbacks = [];

    /**
     * Create a new parallel testing instance.
     */
    public function __construct(
        /**
         * The container instance.
         */
        protected \Illuminate\Contracts\Container\Container $container
    ) {
    }

    /**
     * Set a callback that should be used when resolving options.
     *
     * @param  \Closure|null  $resolver
     */
    public function resolveOptionsUsing($resolver): void
    {
        $this->optionsResolver = $resolver;
    }

    /**
     * Set a callback that should be used when resolving the unique process token.
     *
     * @param  \Closure|null  $resolver
     */
    public function resolveTokenUsing($resolver): void
    {
        $this->tokenResolver = $resolver;
    }

    /**
     * Register a "setUp" process callback.
     *
     * @param  callable  $callback
     */
    public function setUpProcess($callback): void
    {
        $this->setUpProcessCallbacks[] = $callback;
    }

    /**
     * Register a "setUp" test case callback.
     *
     * @param  callable  $callback
     */
    public function setUpTestCase($callback): void
    {
        $this->setUpTestCaseCallbacks[] = $callback;
    }

    /**
     * Register a "setUp" test database callback that runs prior to the migrations.
     *
     * @param  callable  $callback
     */
    public function setUpTestDatabaseBeforeMigrating($callback): void
    {
        $this->setUpTestDatabaseBeforeMigratingCallbacks[] = $callback;
    }

    /**
     * Register a "setUp" test database callback.
     *
     * @param  callable  $callback
     */
    public function setUpTestDatabase($callback): void
    {
        $this->setUpTestDatabaseCallbacks[] = $callback;
    }

    /**
     * Register a "tearDown" process callback.
     *
     * @param  callable  $callback
     */
    public function tearDownProcess($callback): void
    {
        $this->tearDownProcessCallbacks[] = $callback;
    }

    /**
     * Register a "tearDown" test case callback.
     *
     * @param  callable  $callback
     */
    public function tearDownTestCase($callback): void
    {
        $this->tearDownTestCaseCallbacks[] = $callback;
    }

    /**
     * Call all of the "setUp" process callbacks.
     */
    public function callSetUpProcessCallbacks(): void
    {
        $this->whenRunningInParallel(function (): void {
            foreach ($this->setUpProcessCallbacks as $callback) {
                $this->container->call($callback, [
                    'token' => $this->token(),
                ]);
            }
        });
    }

    /**
     * Call all of the "setUp" test case callbacks.
     *
     * @param  \Illuminate\Foundation\Testing\TestCase  $testCase
     */
    public function callSetUpTestCaseCallbacks($testCase): void
    {
        $this->whenRunningInParallel(function () use ($testCase): void {
            foreach ($this->setUpTestCaseCallbacks as $callback) {
                $this->container->call($callback, [
                    'testCase' => $testCase,
                    'token' => $this->token(),
                ]);
            }
        });
    }

    /**
     * Call all of the "setUp" test database callbacks that run prior to migrations.
     *
     * @param  string  $database
     */
    public function callSetUpTestDatabaseBeforeMigratingCallbacks($database): void
    {
        $this->whenRunningInParallel(function () use ($database): void {
            foreach ($this->setUpTestDatabaseBeforeMigratingCallbacks as $callback) {
                $this->container->call($callback, [
                    'database' => $database,
                    'token' => $this->token(),
                ]);
            }
        });
    }

    /**
     * Call all of the "setUp" test database callbacks.
     *
     * @param  string  $database
     */
    public function callSetUpTestDatabaseCallbacks($database): void
    {
        $this->whenRunningInParallel(function () use ($database): void {
            foreach ($this->setUpTestDatabaseCallbacks as $callback) {
                $this->container->call($callback, [
                    'database' => $database,
                    'token' => $this->token(),
                ]);
            }
        });
    }

    /**
     * Call all of the "tearDown" process callbacks.
     */
    public function callTearDownProcessCallbacks(): void
    {
        $this->whenRunningInParallel(function (): void {
            foreach ($this->tearDownProcessCallbacks as $callback) {
                $this->container->call($callback, [
                    'token' => $this->token(),
                ]);
            }
        });
    }

    /**
     * Call all of the "tearDown" test case callbacks.
     *
     * @param  \Illuminate\Foundation\Testing\TestCase  $testCase
     */
    public function callTearDownTestCaseCallbacks($testCase): void
    {
        $this->whenRunningInParallel(function () use ($testCase): void {
            foreach ($this->tearDownTestCaseCallbacks as $callback) {
                $this->container->call($callback, [
                    'testCase' => $testCase,
                    'token' => $this->token(),
                ]);
            }
        });
    }

    /**
     * Get a parallel testing option.
     *
     * @param  string  $option
     * @return mixed
     */
    public function option($option)
    {
        $optionsResolver = $this->optionsResolver ?: function ($option) {
            $option = 'LARAVEL_PARALLEL_TESTING_'.Str::upper($option);

            return $_SERVER[$option] ?? false;
        };

        return $optionsResolver($option);
    }

    /**
     * Gets a unique test token.
     *
     * @return string|false
     */
    public function token()
    {
        return $this->tokenResolver
            ? call_user_func($this->tokenResolver)
            : ($_SERVER['TEST_TOKEN'] ?? false);
    }

    /**
     * Apply the callback if tests are running in parallel.
     *
     * @param  callable  $callback
     * @return void
     */
    protected function whenRunningInParallel($callback)
    {
        if ($this->inParallel()) {
            $callback();
        }
    }

    /**
     * Indicates if the current tests are been run in parallel.
     */
    protected function inParallel(): bool
    {
        return ! empty($_SERVER['LARAVEL_PARALLEL_TESTING']) && $this->token();
    }
}
