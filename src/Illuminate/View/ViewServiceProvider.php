<?php

namespace Illuminate\View;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\FileEngine;
use Illuminate\View\Engines\PhpEngine;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->registerFactory();
        $this->registerViewFinder();
        $this->registerBladeCompiler();
        $this->registerEngineResolver();

        $this->app->terminating(static function (): void {
            Component::flushCache();
        });
    }

    /**
     * Register the view environment.
     */
    public function registerFactory(): void
    {
        $this->app->singleton('view', function ($app) {
            // Next we need to grab the engine resolver instance that will be used by the
            // environment. The resolver will be used by an environment to get each of
            // the various engine implementations such as plain PHP or Blade engine.
            $resolver = $app['view.engine.resolver'];

            $finder = $app['view.finder'];

            $factory = $this->createFactory($resolver, $finder, $app['events']);

            // We will also set the container instance on this view environment since the
            // view composers may be classes registered in the container, which allows
            // for great testable, flexible composers for the application developer.
            $factory->setContainer($app);

            $factory->share('app', $app);

            $app->terminating(static function (): void {
                Component::forgetFactory();
            });

            return $factory;
        });
    }

    /**
     * Create a new Factory Instance.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @param  \Illuminate\View\ViewFinderInterface  $finder
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     */
    protected function createFactory($resolver, $finder, $events): \Illuminate\View\Factory
    {
        return new Factory($resolver, $finder, $events);
    }

    /**
     * Register the view finder implementation.
     */
    public function registerViewFinder(): void
    {
        $this->app->bind('view.finder', fn($app) => new FileViewFinder($app['files'], $app['config']['view.paths']));
    }

    /**
     * Register the Blade compiler implementation.
     */
    public function registerBladeCompiler(): void
    {
        $this->app->singleton('blade.compiler', fn($app) => tap(new BladeCompiler(
            $app['files'],
            $app['config']['view.compiled'],
            $app['config']->get('view.relative_hash', false) ? $app->basePath() : '',
            $app['config']->get('view.cache', true),
            $app['config']->get('view.compiled_extension', 'php'),
            $app['config']->get('view.check_cache_timestamps', true),
        ), function ($blade): void {
            $blade->component('dynamic-component', DynamicComponent::class);
        }));
    }

    /**
     * Register the engine resolver instance.
     */
    public function registerEngineResolver(): void
    {
        $this->app->singleton('view.engine.resolver', function (): \Illuminate\View\Engines\EngineResolver {
            $resolver = new EngineResolver;

            // Next, we will register the various view engines with the resolver so that the
            // environment will resolve the engines needed for various views based on the
            // extension of view file. We call a method for each of the view's engines.
            foreach (['file', 'php', 'blade'] as $engine) {
                $this->{'register'.ucfirst($engine).'Engine'}($resolver);
            }

            return $resolver;
        });
    }

    /**
     * Register the file engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     */
    public function registerFileEngine($resolver): void
    {
        $resolver->register('file', fn() => new FileEngine(Container::getInstance()->make('files')));
    }

    /**
     * Register the PHP engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     */
    public function registerPhpEngine($resolver): void
    {
        $resolver->register('php', fn() => new PhpEngine(Container::getInstance()->make('files')));
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     */
    public function registerBladeEngine($resolver): void
    {
        $resolver->register('blade', function (): \Illuminate\View\Engines\CompilerEngine {
            $app = Container::getInstance();

            $compiler = new CompilerEngine(
                $app->make('blade.compiler'),
                $app->make('files'),
            );

            $app->terminating(static function () use ($compiler): void {
                $compiler->forgetCompiledOrNotExpired();
            });

            return $compiler;
        });
    }
}
