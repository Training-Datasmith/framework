<?php

namespace Illuminate\Database;

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector as ConcurrencyErrorDetectorContract;
use Illuminate\Contracts\Database\LostConnectionDetector as LostConnectionDetectorContract;
use Illuminate\Contracts\Queue\EntityResolver;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\QueueEntityResolver;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * The array of resolved Faker instances.
     *
     * @var array
     */
    protected static $fakers = [];

    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        Model::setConnectionResolver($this->app['db']);

        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        Model::clearBootedModels();

        $this->registerConnectionServices();
        $this->registerFakerGenerator();
        $this->registerQueueableEntityResolver();
    }

    /**
     * Register the primary database bindings.
     *
     * @return void
     */
    protected function registerConnectionServices()
    {
        // The connection factory is used to create the actual connection instances on
        // the database. We will inject the factory into the manager so that it may
        // make the connections while they are actually needed and not of before.
        $this->app->singleton('db.factory', fn($app) => new ConnectionFactory($app));

        // The database manager is used to resolve various connections, since multiple
        // connections might be managed. It also implements the connection resolver
        // interface which may be used by other components requiring connections.
        $this->app->singleton('db', fn($app) => new DatabaseManager($app, $app['db.factory']));

        $this->app->bind('db.connection', fn($app) => $app['db']->connection());

        $this->app->bind('db.schema', fn($app) => $app['db']->connection()->getSchemaBuilder());

        $this->app->singleton('db.transactions', fn() => new DatabaseTransactionsManager);

        $this->app->singleton(ConcurrencyErrorDetectorContract::class, fn() => new ConcurrencyErrorDetector);

        $this->app->singleton(LostConnectionDetectorContract::class, fn() => new LostConnectionDetector);
    }

    /**
     * Register the Faker Generator instance in the container.
     *
     * @return void
     */
    protected function registerFakerGenerator()
    {
        if (! class_exists(FakerGenerator::class)) {
            return;
        }

        $this->app->singleton(FakerGenerator::class, function (array $app, array $parameters) {
            $locale = $parameters['locale'] ?? $app['config']->get('app.faker_locale', 'en_US');

            if (! isset(static::$fakers[$locale])) {
                static::$fakers[$locale] = FakerFactory::create($locale);
            }

            static::$fakers[$locale]->unique(true);

            return static::$fakers[$locale];
        });
    }

    /**
     * Register the queueable entity resolver implementation.
     *
     * @return void
     */
    protected function registerQueueableEntityResolver()
    {
        $this->app->singleton(EntityResolver::class, fn() => new QueueEntityResolver);
    }
}
