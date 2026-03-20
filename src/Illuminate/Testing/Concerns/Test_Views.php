<?php

declare(strict_types=1);

namespace Illuminate\Testing\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;

trait TestViews
{
    /**
     * The original compiled view path prior to appending the token.
     *
     * @var string|null
     */
    protected static $originalCompiledViewPath;

    /**
     * Boot test views for parallel testing.
     *
     * @return void
     */
    protected function bootTestViews()
    {
        ParallelTesting::setUpProcess(function (): void {
            if ($path = $this->parallelSafeCompiledViewPath()) {
                File::ensureDirectoryExists($path);
            }
        });

        ParallelTesting::setUpTestCase(function (): void {
            if ($path = $this->parallelSafeCompiledViewPath()) {
                $this->switchToCompiledViewPath($path);
            }
        });

        ParallelTesting::tearDownProcess(function (): void {
            if ($path = $this->parallelSafeCompiledViewPath()) {
                File::deleteDirectory($path);
            }
        });
    }

    /**
     * Get the test compiled view path.
     */
    protected function parallelSafeCompiledViewPath(): ?string
    {
        self::$originalCompiledViewPath ??= $this->app['config']->get('view.compiled', '');

        if (! self::$originalCompiledViewPath) {
            return null;
        }

        return rtrim(self::$originalCompiledViewPath, '\/')
            .'/test_'
            .ParallelTesting::token();
    }

    /**
     * Switch to the given compiled view path.
     *
     * @param  string  $path
     * @return void
     */
    protected function switchToCompiledViewPath($path)
    {
        $this->app['config']->set('view.compiled', $path);

        if ($this->app->resolved('blade.compiler')) {
            $compiler = $this->app['blade.compiler'];

            (function () use ($path): void {
                $this->cachePath = $path;
            })->bindTo($compiler, $compiler)();
        }
    }
}
