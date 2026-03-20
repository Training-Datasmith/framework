<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Composer\Installer\Package_Event;
use Composer\IO\Io_Interface;
use Composer\Script\Event;
use Illuminate\Concurrency\Process_Driver;
use Illuminate\Encryption\Encryption_Service_Provider;
use Illuminate\Foundation\Bootstrap\Load_Configuration;
use Illuminate\Foundation\Bootstrap\Load_Environment_Variables;
use Throwable;
class Composer_Scripts
{
    /**
     * Handle the post-install Composer event.
     */
    public static function post_install(Event $event): void
    {
        require_once $event->get_composer()->get_config()->get('vendor-dir') . '/autoload.php';
        static::clear_compiled();
    }
    /**
     * Handle the post-update Composer event.
     */
    public static function post_update(Event $event): void
    {
        require_once $event->get_composer()->get_config()->get('vendor-dir') . '/autoload.php';
        static::clear_compiled();
    }
    /**
     * Handle the post-autoload-dump Composer event.
     */
    public static function post_autoload_dump(Event $event): void
    {
        require_once $event->get_composer()->get_config()->get('vendor-dir') . '/autoload.php';
        static::clear_compiled();
    }
    /**
     * Handle the pre-package-uninstall Composer event.
     */
    public static function pre_package_uninstall(Package_Event $event): void
    {
        // Package uninstall events are only applicable when uninstalling packages in dev environments...
        if (!$event->is_dev_mode()) {
            return;
        }
        $event_name = null;
        try {
            require_once $event->get_composer()->get_config()->get('vendor-dir') . '/autoload.php';
            $laravel = new Application(getcwd());
            $laravel->bootstrap_with([Load_Environment_Variables::class, Load_Configuration::class]);
            // Ensure we can encrypt our serializable closure...
            (new Encryption_Service_Provider($laravel))->register();
            $name = $event->get_operation()->get_package()->get_name();
            $event_name = "composer_package.{$name}:pre_uninstall";
            $laravel->make(Process_Driver::class)->run(static fn() => app()['events']->dispatch($event_name));
        } catch (Throwable $e) {
            // Ignore any errors to allow the package removal to complete...
            $event->get_io()->write('There was an error dispatching or handling the [' . ($event_name ?? 'unknown') . '] event. Continuing with package removal...');
            $event->get_io()->write_error('Exception message: ' . $e->get_message(), verbosity: Io_Interface::VERBOSE);
            // @phpstan-ignore class.notFound (Composer exists if this is running)
        }
    }
    /**
     * Clear the cached Laravel bootstrapping files.
     *
     * @return void
     */
    protected static function clear_compiled()
    {
        $laravel = new Application(getcwd());
        if (is_file($config_path = $laravel->get_cached_config_path())) {
            @unlink($config_path);
        }
        if (is_file($services_path = $laravel->get_cached_services_path())) {
            @unlink($services_path);
        }
        if (is_file($packages_path = $laravel->get_cached_packages_path())) {
            @unlink($packages_path);
        }
    }
}