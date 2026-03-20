<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Configuration;

use Closure;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Client\Request_Exception;
use Illuminate\Support\Arr;
class Exceptions
{
    /**
     * Create a new exception handling configuration instance.
     */
    public function __construct(public Handler $handler)
    {
    }
    /**
     * Register a reportable callback.
     *
     * @return \Illuminate\Foundation\Exceptions\ReportableHandler
     */
    public function report(callable $using)
    {
        return $this->handler->reportable($using);
    }
    /**
     * Register a reportable callback.
     *
     * @return \Illuminate\Foundation\Exceptions\ReportableHandler
     */
    public function reportable(callable $report_using)
    {
        return $this->handler->reportable($report_using);
    }
    /**
     * Register a renderable callback.
     *
     * @return $this
     */
    public function render(callable $using): static
    {
        $this->handler->renderable($using);
        return $this;
    }
    /**
     * Register a renderable callback.
     *
     * @return $this
     */
    public function renderable(callable $render_using): static
    {
        $this->handler->renderable($render_using);
        return $this;
    }
    /**
     * Register a callback to prepare the final, rendered exception response.
     *
     * @return $this
     */
    public function respond(callable $using): static
    {
        $this->handler->respond_using($using);
        return $this;
    }
    /**
     * Specify the callback that should be used to throttle reportable exceptions.
     *
     * @return $this
     */
    public function throttle(callable $throttle_using): static
    {
        $this->handler->throttle_using($throttle_using);
        return $this;
    }
    /**
     * Register a new exception mapping.
     *
     * @param  \Closure|string  $from
     * @param  \Closure|string|null  $to
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function map($from, $to = null): static
    {
        $this->handler->map($from, $to);
        return $this;
    }
    /**
     * Set the log level for the given exception type.
     *
     * @param  class-string<\Throwable>  $type
     * @param  \Psr\Log\LogLevel::*  $level
     * @return $this
     */
    public function level(string $type, string $level): static
    {
        $this->handler->level($type, $level);
        return $this;
    }
    /**
     * Register a closure that should be used to build exception context data.
     *
     * @return $this
     */
    public function context(Closure $context_callback): static
    {
        $this->handler->build_context_using($context_callback);
        return $this;
    }
    /**
     * Indicate that the given exception type should not be reported.
     *
     * @return $this
     */
    public function dont_report(array|string $class): static
    {
        foreach (Arr::wrap($class) as $exception_class) {
            $this->handler->dont_report($exception_class);
        }
        return $this;
    }
    /**
     * Register a callback to determine if an exception should not be reported.
     *
     * @param  (\Closure(\Throwable): bool)  $dontReportWhen
     * @return $this
     */
    public function dont_report_when(Closure $dont_report_when): static
    {
        $this->handler->dont_report_when($dont_report_when);
        return $this;
    }
    /**
     * Do not report duplicate exceptions.
     *
     * @return $this
     */
    public function dont_report_duplicates(): static
    {
        $this->handler->dont_report_duplicates();
        return $this;
    }
    /**
     * Indicate that the given attributes should never be flashed to the session on validation errors.
     *
     * @return $this
     */
    public function dont_flash(array|string $attributes): static
    {
        $this->handler->dont_flash($attributes);
        return $this;
    }
    /**
     * Register the callable that determines if the exception handler response should be JSON.
     *
     * @param  callable(\Illuminate\Http\Request $request, \Throwable): bool  $callback
     * @return $this
     */
    public function should_render_json_when(callable $callback): static
    {
        $this->handler->should_render_json_when($callback);
        return $this;
    }
    /**
     * Indicate that the given exception class should not be ignored.
     *
     * @param  array<int, class-string<\Throwable>>|class-string<\Throwable>  $class
     * @return $this
     */
    public function stop_ignoring(array|string $class): static
    {
        $this->handler->stop_ignoring($class);
        return $this;
    }
    /**
     * Set the truncation length for request exception messages.
     *
     * @return $this
     */
    public function truncate_request_exceptions_at(int $length): static
    {
        Request_Exception::truncate_at($length);
        return $this;
    }
    /**
     * Disable truncation of request exception messages.
     *
     * @return $this
     */
    public function dont_truncate_request_exceptions(): static
    {
        Request_Exception::dont_truncate();
        return $this;
    }
}