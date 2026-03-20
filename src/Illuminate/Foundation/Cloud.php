<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Bootstrap\Handle_Exceptions;
use Illuminate\Foundation\Bootstrap\Load_Configuration;
use Monolog\Formatter\Json_Formatter;
use Monolog\Handler\Socket_Handler;
use PDO;
class Cloud
{
    /**
     * Handle a bootstrapper that is bootstrapping.
     */
    public static function bootstrapper_bootstrapping(Application $app, string $bootstrapper): void
    {
    }
    /**
     * Handle a bootstrapper that has bootstrapped.
     */
    public static function bootstrapper_bootstrapped(Application $app, string $bootstrapper): void
    {
        (match ($bootstrapper) {
            Load_Configuration::class => function () use ($app): void {
                static::configure_disks($app);
                static::configure_unpooled_postgres_connection($app);
                static::ensure_migrations_use_unpooled_connection($app);
            },
            Handle_Exceptions::class => function () use ($app): void {
                static::configure_cloud_logging($app);
            },
            default => fn(): true => true,
        })();
    }
    /**
     * Configure the Laravel Cloud disks if applicable.
     */
    public static function configure_disks(Application $app): void
    {
        if (!isset($_SERVER['LARAVEL_CLOUD_DISK_CONFIG'])) {
            return;
        }
        $disks = json_decode((string) $_SERVER['LARAVEL_CLOUD_DISK_CONFIG'], true);
        foreach ($disks as $disk) {
            $app['config']->set('filesystems.disks.' . $disk['disk'], ['driver' => 's3', 'key' => $disk['access_key_id'], 'secret' => $disk['access_key_secret'], 'bucket' => $disk['bucket'], 'url' => $disk['url'], 'endpoint' => $disk['endpoint'], 'region' => 'auto', 'use_path_style_endpoint' => false, 'throw' => false, 'report' => false]);
            if ($disk['is_default'] ?? false) {
                $app['config']->set('filesystems.default', $disk['disk']);
            }
        }
    }
    /**
     * Configure the unpooled Laravel Postgres connection if applicable.
     */
    public static function configure_unpooled_postgres_connection(Application $app): void
    {
        $host = $app['config']->get('database.connections.pgsql.host', '');
        if (str_contains((string) $host, 'pg.laravel.cloud') && str_contains((string) $host, '-pooler')) {
            $app['config']->set('database.connections.pgsql-unpooled', array_merge($app['config']->get('database.connections.pgsql'), ['host' => str_replace('-pooler', '', $host)]));
            $app['config']->set('database.connections.pgsql.options', array_merge($app['config']->get('database.connections.pgsql.options', []), [PDO::ATTR_EMULATE_PREPARES => true]));
        }
    }
    /**
     * Ensure that migrations use the unpooled Postgres connection if applicable.
     */
    public static function ensure_migrations_use_unpooled_connection(Application $app): void
    {
        if (!is_array($app['config']->get('database.connections.pgsql-unpooled'))) {
            return;
        }
        Migrator::resolve_connections_using(function ($resolver, $connection) use ($app) {
            $connection ??= $app['config']->get('database.default');
            return $resolver->connection($connection === 'pgsql' ? 'pgsql-unpooled' : $connection);
        });
    }
    /**
     * Configure the Laravel Cloud log channels.
     */
    public static function configure_cloud_logging(Application $app): void
    {
        $app['config']->set('logging.channels.stderr.formatter_with', ['includeStacktraces' => true]);
        $app['config']->set('logging.channels.laravel-cloud-socket', ['driver' => 'monolog', 'level' => $_ENV['LOG_LEVEL'] ?? $_SERVER['LOG_LEVEL'] ?? 'debug', 'handler' => Socket_Handler::class, 'formatter' => Json_Formatter::class, 'formatter_with' => ['includeStacktraces' => true], 'with' => ['connectionString' => $_ENV['LARAVEL_CLOUD_LOG_SOCKET'] ?? $_SERVER['LARAVEL_CLOUD_LOG_SOCKET'] ?? 'unix:///tmp/cloud-init.sock', 'persistent' => true]]);
    }
}