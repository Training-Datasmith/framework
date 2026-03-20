<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Closure;
use Illuminate\Contracts\Debug\Exception_Handler;
use Illuminate\Support\Testing\Fakes\Exception_Handler_Fake;
use Illuminate\Support\Traits\Reflects_Closures;
use Illuminate\Testing\Assert;
use Illuminate\Validation\Validation_Exception;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Throwable;
trait Interacts_With_Exception_Handling
{
    use Reflects_Closures;
    /**
     * The original exception handler.
     *
     * @var \Illuminate\Contracts\Debug\ExceptionHandler|null
     */
    protected $original_exception_handler;
    /**
     * Restore exception handling.
     *
     * @return $this
     */
    protected function with_exception_handling()
    {
        if ($this->original_exception_handler) {
            $current_exception_handler = app(Exception_Handler::class);
            $current_exception_handler instanceof Exception_Handler_Fake ? $current_exception_handler->set_handler($this->original_exception_handler) : $this->app->instance(Exception_Handler::class, $this->original_exception_handler);
        }
        return $this;
    }
    /**
     * Only handle the given exceptions via the exception handler.
     *
     * @param  list<class-string<\Throwable>>  $exceptions
     * @return $this
     */
    protected function handle_exceptions(array $exceptions)
    {
        return $this->without_exception_handling($exceptions);
    }
    /**
     * Only handle validation exceptions via the exception handler.
     *
     * @return $this
     */
    protected function handle_validation_exceptions()
    {
        return $this->handle_exceptions([Validation_Exception::class]);
    }
    /**
     * Disable exception handling for the test.
     *
     * @param  list<class-string<\Throwable>>  $except
     * @return $this
     */
    protected function without_exception_handling(array $except = [])
    {
        if ($this->original_exception_handler == null) {
            $current_exception_handler = app(Exception_Handler::class);
            $this->original_exception_handler = $current_exception_handler instanceof Exception_Handler_Fake ? $current_exception_handler->handler() : $current_exception_handler;
        }
        $exception_handler = new class($this->original_exception_handler, $except) implements Exception_Handler, Without_Exception_Handling_Handler
        {
            /**
             * Create a new class instance.
             *
             * @param  \Illuminate\Contracts\Debug\ExceptionHandler  $originalHandler
             * @param  list<class-string<\Throwable>>  $except
             */
            public function __construct(protected $original_handler, protected $except = [])
            {
            }
            /**
             * Report or log an exception.
             *
             *
             * @throws \Exception
             */
            public function report(Throwable $e): void
            {
            }
            /**
             * Determine if the exception should be reported.
             *
             * @return false
             */
            public function should_report(Throwable $e): bool
            {
                return false;
            }
            /**
             * Render an exception into an HTTP response.
             *
             * @param  \Illuminate\Http\Request  $request
             * @return \Symfony\Component\HttpFoundation\Response
             * @throws \Throwable
             */
            public function render($request, Throwable $e)
            {
                foreach ($this->except as $class) {
                    if ($e instanceof $class) {
                        return $this->original_handler->render($request, $e);
                    }
                }
                if ($e instanceof Not_Found_Http_Exception) {
                    throw new Not_Found_Http_Exception("{$request->method()} {$request->url()}", $e, is_int($e->get_code()) ? $e->get_code() : 0);
                }
                throw $e;
            }
            /**
             * Render an exception to the console.
             *
             * @param  \Symfony\Component\Console\Output\OutputInterface  $output
             */
            public function render_for_console($output, Throwable $e): void
            {
                (new Console_Application())->render_throwable($e, $output);
            }
        };
        $current_exception_handler = app(Exception_Handler::class);
        $current_exception_handler instanceof Exception_Handler_Fake ? $current_exception_handler->set_handler($exception_handler) : $this->app->instance(Exception_Handler::class, $exception_handler);
        return $this;
    }
    /**
     * Assert that the given callback throws an exception with the given message when invoked.
     *
     * @param  (\Closure(\Throwable): bool)|class-string<\Throwable>  $expectedClass
     * @return $this
     */
    protected function assert_throws(Closure $test, string|Closure $expected_class = Throwable::class, ?string $expected_message = null)
    {
        [$expected_class, $expected_class_callback] = $expected_class instanceof Closure ? [$this->first_closure_parameter_type($expected_class), $expected_class] : [$expected_class, null];
        try {
            $test();
            $thrown = false;
        } catch (Throwable $exception) {
            $thrown = $exception instanceof $expected_class && ($expected_class_callback === null || $expected_class_callback($exception));
            $actual_message = $exception->get_message();
        }
        Assert::assert_true($thrown, sprintf('Failed asserting that exception of type "%s" was thrown.', $expected_class));
        if (isset($expected_message)) {
            if (!isset($actual_message)) {
                Assert::fail(sprintf('Failed asserting that exception of type "%s" with message "%s" was thrown.', $expected_class, $expected_message));
            } else {
                Assert::assert_string_contains_string($expected_message, $actual_message);
            }
        }
        return $this;
    }
    /**
     * Assert that the given callback does not throw an exception.
     *
     * @return $this
     */
    protected function assert_doesnt_throw(Closure $test)
    {
        try {
            $test();
            $thrown = false;
        } catch (Throwable $exception) {
            $thrown = true;
            $exception_class = $exception::class;
            $exception_message = $exception->get_message();
        }
        Assert::assert_true(!$thrown, sprintf('Unexpected exception of type %s with message %s was thrown.', $exception_class ?? null, $exception_message ?? null));
        return $this;
    }
}