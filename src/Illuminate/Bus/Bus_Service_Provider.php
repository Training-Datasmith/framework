<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Aws\Dynamo_Db\Dynamo_Db_Client;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Bus\Queueing_Dispatcher as QueueingDispatcherContract;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Support\Arr;
use Illuminate\Support\Service_Provider;
class Bus_Service_Provider extends Service_Provider implements Deferrable_Provider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(Dispatcher::class, fn($app): \Illuminate\Bus\Dispatcher => new Dispatcher($app, fn($connection = null) => Container::get_instance()->make(Queue_Factory_Contract::class)->connection($connection)));
        $this->register_batch_services();
        $this->app->alias(Dispatcher::class, Dispatcher_Contract::class);
        $this->app->alias(Dispatcher::class, Queueing_Dispatcher_Contract::class);
    }
    /**
     * Register the batch handling services.
     *
     * @return void
     */
    protected function register_batch_services()
    {
        $this->app->singleton(Batch_Repository::class, function ($app) {
            $driver = $app->config->get('queue.batching.driver', 'database');
            return $driver === 'dynamodb' ? $app->make(Dynamo_Batch_Repository::class) : $app->make(Database_Batch_Repository::class);
        });
        $this->app->singleton(Database_Batch_Repository::class, fn($app): \Illuminate\Bus\Database_Batch_Repository => new Database_Batch_Repository($app->make(Batch_Factory::class), $app->make('db')->connection($app->config->get('queue.batching.database')), $app->config->get('queue.batching.table', 'job_batches')));
        $this->app->singleton(Dynamo_Batch_Repository::class, function ($app): \Illuminate\Bus\Dynamo_Batch_Repository {
            $config = $app->config->get('queue.batching');
            $dynamo_config = ['region' => $config['region'], 'version' => 'latest', 'endpoint' => $config['endpoint'] ?? null];
            if (!empty($config['key']) && !empty($config['secret'])) {
                $dynamo_config['credentials'] = Arr::only($config, ['key', 'secret']);
                if (!empty($config['token'])) {
                    $dynamo_config['credentials']['token'] = $config['token'];
                }
            }
            return new Dynamo_Batch_Repository($app->make(Batch_Factory::class), new Dynamo_Db_Client($dynamo_config), $app->config->get('app.name'), $app->config->get('queue.batching.table', 'job_batches'), ttl: $app->config->get('queue.batching.ttl', null), ttlAttribute: $app->config->get('queue.batching.ttl_attribute', 'ttl'));
        });
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [Dispatcher::class, Dispatcher_Contract::class, Queueing_Dispatcher_Contract::class, Batch_Repository::class, Database_Batch_Repository::class];
    }
}