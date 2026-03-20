<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bootstrap;

use ErrorException;
use Exception;
use Illuminate\Contracts\Debug\Exception_Handler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Log_Manager;
use Illuminate\Support\Env;
use Monolog\Handler\Null_Handler;
use Php_Unit\Framework\Test_Case;
use Php_Unit\Runner\Error_Handler;
use Php_Unit\Runner\Version;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Error_Handler\Error\Fatal_Error;
use Throwable;
class Handle_Exceptions
{
    /**
     * Reserved memory so that errors can be displayed properly on memory exhaustion.
     *
     * @var string|null
     */
    public static $reserved_memory;
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected static $app;
    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app): void
    {
        static::$reserved_memory = str_repeat('x', 32768);
        static::$app = $app;
        error_reporting(-1);
        set_error_handler($this->forwards_to('handleError'));
        set_exception_handler($this->forwards_to('handleException'));
        register_shutdown_function($this->forwards_to('handleShutdown'));
        if (!$app->environment('testing')) {
            ini_set('display_errors', 'Off');
        }
    }
    /**
     * Report PHP deprecations, or convert PHP errors to ErrorException instances.
     *
     * @param  int  $level
     * @param  string  $message
     * @param  string  $file
     * @param  int  $line
     *
     * @throws \ErrorException
     */
    public function handle_error($level, $message, $file = '', $line = 0): void
    {
        if ($this->is_deprecation($level)) {
            $this->handle_deprecation_error($message, $file, $line, $level);
        } elseif (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }
    /**
     * Reports a deprecation to the "deprecations" logger.
     *
     * @param  string  $message
     * @param  string  $file
     * @param  int  $line
     * @param  int  $level
     */
    public function handle_deprecation_error($message, $file, $line, $level = E_DEPRECATED): void
    {
        if ($this->should_ignore_deprecation_errors()) {
            return;
        }
        try {
            $logger = static::$app->make(Log_Manager::class);
        } catch (Exception) {
            return;
        }
        $this->ensure_deprecation_logger_is_configured();
        $options = static::$app['config']->get('logging.deprecations') ?? [];
        with($logger->channel('deprecations'), function ($log) use ($message, $file, $line, $level, $options): void {
            if ($options['trace'] ?? false) {
                $log->warning((string) new ErrorException($message, 0, $level, $file, $line));
            } else {
                $log->warning(sprintf('%s in %s on line %s', $message, $file, $line));
            }
        });
    }
    /**
     * Determine if deprecation errors should be ignored.
     */
    protected function should_ignore_deprecation_errors(): bool
    {
        if (!class_exists(Log_Manager::class)) {
            return true;
        }
        if (!static::$app->has_been_bootstrapped()) {
            return true;
        }
        return static::$app->running_unit_tests() && !Env::get('LOG_DEPRECATIONS_WHILE_TESTING');
    }
    /**
     * Ensure the "deprecations" logger is configured.
     *
     * @return void
     */
    protected function ensure_deprecation_logger_is_configured()
    {
        $config = static::$app['config'];
        if ($config->get('logging.channels.deprecations')) {
            return;
        }
        $this->ensure_null_log_driver_is_configured();
        if (is_array($options = $config->get('logging.deprecations'))) {
            $driver = $options['channel'] ?? 'null';
        } else {
            $driver = $options ?? 'null';
        }
        $config->set('logging.channels.deprecations', $config->get("logging.channels.{$driver}"));
    }
    /**
     * Ensure the "null" log driver is configured.
     *
     * @return void
     */
    protected function ensure_null_log_driver_is_configured()
    {
        $config = static::$app['config'];
        if ($config->get('logging.channels.null')) {
            return;
        }
        $config->set('logging.channels.null', ['driver' => 'monolog', 'handler' => Null_Handler::class]);
    }
    /**
     * Handle an uncaught exception from the application.
     *
     * Note: Most exceptions can be handled via the try / catch block in
     * the HTTP and Console kernels. But, fatal error exceptions must
     * be handled differently since they are not normal exceptions.
     */
    public function handle_exception(Throwable $e): void
    {
        static::$reserved_memory = null;
        try {
            $this->get_exception_handler()->report($e);
        } catch (Exception) {
            $exception_handler_failed = true;
        }
        if (static::$app->running_in_console()) {
            $this->render_for_console($e);
            if ($exception_handler_failed ?? false) {
                exit(1);
            }
        } else {
            $this->render_http_response($e);
        }
    }
    /**
     * Render an exception to the console.
     *
     * @return void
     */
    protected function render_for_console(Throwable $e)
    {
        $this->get_exception_handler()->render_for_console(new Console_Output(), $e);
    }
    /**
     * Render an exception as an HTTP response and send it.
     *
     * @return void
     */
    protected function render_http_response(Throwable $e)
    {
        $this->get_exception_handler()->render(static::$app['request'], $e)->send();
    }
    /**
     * Handle the PHP shutdown event.
     */
    public function handle_shutdown(): void
    {
        static::$reserved_memory = null;
        if (!is_null($error = error_get_last()) && $this->is_fatal($error['type'])) {
            $this->handle_exception($this->fatal_error_from_php_error($error, 0));
        }
    }
    /**
     * Create a new fatal error instance from an error array.
     *
     * @param  int|null  $traceOffset
     * @return \Symfony\Component\ErrorHandler\Error\FatalError
     */
    protected function fatal_error_from_php_error(array $error, $trace_offset = null)
    {
        return new Fatal_Error($error['message'], 0, $error, $trace_offset);
    }
    /**
     * Forward a method call to the given method if an application instance exists.
     *
     * @return callable
     */
    protected function forwards_to($method)
    {
        return fn(...$arguments) => static::$app ? $this->{$method}(...$arguments) : false;
    }
    /**
     * Determine if the error level is a deprecation.
     *
     * @param  int  $level
     */
    protected function is_deprecation($level): bool
    {
        return in_array($level, [E_DEPRECATED, E_USER_DEPRECATED]);
    }
    /**
     * Determine if the error type is fatal.
     *
     * @param  int  $type
     */
    protected function is_fatal($type): bool
    {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE]);
    }
    /**
     * Get an instance of the exception handler.
     *
     * @return \Illuminate\Contracts\Debug\ExceptionHandler
     */
    protected function get_exception_handler()
    {
        return static::$app->make(Exception_Handler::class);
    }
    /**
     * Clear the local application instance from memory.
     *
     *
     * @deprecated This method will be removed in a future Laravel version.
     */
    public static function forget_app(): void
    {
        static::$app = null;
    }
    /**
     * Flush the bootstrapper's global state.
     */
    public static function flush_state(?Test_Case $test_case = null): void
    {
        if (is_null(static::$app)) {
            return;
        }
        static::flush_handlers_state($test_case);
        static::$app = null;
        static::$reserved_memory = null;
    }
    /**
     * Flush the bootstrapper's global handlers state.
     */
    public static function flush_handlers_state(?Test_Case $test_case = null): void
    {
        while (get_exception_handler() !== null) {
            restore_exception_handler();
        }
        while (get_error_handler() !== null) {
            restore_error_handler();
        }
        if (class_exists(Error_Handler::class)) {
            $instance = Error_Handler::instance();
            if ((fn() => $this->enabled ?? false)->call($instance)) {
                $instance->disable();
                if (version_compare(Version::id(), '12.3.4', '>=')) {
                    $instance->enable($test_case);
                } else {
                    $instance->enable();
                }
            }
        }
    }
}