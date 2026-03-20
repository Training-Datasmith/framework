<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Illuminate\Contracts\Database\Concurrency_Error_Detector as ConcurrencyErrorDetectorContract;
use Illuminate\Contracts\Database\Lost_Connection_Detector as LostConnectionDetectorContract;
use Illuminate\Contracts\Queue\Entity_Resolver;
use Illuminate\Database\Connectors\Connection_Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Queue_Entity_Resolver;
use Illuminate\Support\Service_Provider;
class Database_Service_Provider extends Service_Provider
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
        Model::set_connection_resolver($this->app['db']);
        Model::set_event_dispatcher($this->app['events']);
    }
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        Model::clear_booted_models();
        $this->register_connection_services();
        $this->register_faker_generator();
        $this->register_queueable_entity_resolver();
    }
    /**
     * Register the primary database bindings.
     *
     * @return void
     */
    protected function register_connection_services()
    {
        // The connection factory is used to create the actual connection instances on
        // the database. We will inject the factory into the manager so that it may
        // make the connections while they are actually needed and not of before.
        $this->app->singleton('db.factory', fn($app): \Illuminate\Database\Connectors\Connection_Factory => new Connection_Factory($app));
        // The database manager is used to resolve various connections, since multiple
        // connections might be managed. It also implements the connection resolver
        // interface which may be used by other components requiring connections.
        $this->app->singleton('db', fn($app): \Illuminate\Database\Database_Manager => new Database_Manager($app, $app['db.factory']));
        $this->app->bind('db.connection', fn($app) => $app['db']->connection());
        $this->app->bind('db.schema', fn($app) => $app['db']->connection()->get_schema_builder());
        $this->app->singleton('db.transactions', fn(): \Illuminate\Database\Database_Transactions_Manager => new Database_Transactions_Manager());
        $this->app->singleton(Concurrency_Error_Detector_Contract::class, fn(): \Illuminate\Database\Concurrency_Error_Detector => new Concurrency_Error_Detector());
        $this->app->singleton(Lost_Connection_Detector_Contract::class, fn(): \Illuminate\Database\Lost_Connection_Detector => new Lost_Connection_Detector());
    }
    /**
     * Register the Faker Generator instance in the container.
     *
     * @return void
     */
    protected function register_faker_generator()
    {
        if (!class_exists(Faker_Generator::class)) {
            return;
        }
        $this->app->singleton(Faker_Generator::class, function (array $app, array $parameters) {
            $locale = $parameters['locale'] ?? $app['config']->get('app.faker_locale', 'en_US');
            if (!isset(static::$fakers[$locale])) {
                static::$fakers[$locale] = Faker_Factory::create($locale);
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
    protected function register_queueable_entity_resolver()
    {
        $this->app->singleton(Entity_Resolver::class, fn(): \Illuminate\Database\Eloquent\Queue_Entity_Resolver => new Queue_Entity_Resolver());
    }
}