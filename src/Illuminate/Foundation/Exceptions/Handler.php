<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions;

use Closure;
use Exception;
use Illuminate\Auth\Access\Authorization_Exception;
use Illuminate\Auth\Authentication_Exception;
use Illuminate\Cache\Rate_Limiter;
use Illuminate\Cache\Rate_Limiting\Limit;
use Illuminate\Cache\Rate_Limiting\Unlimited;
use Illuminate\Console\View\Components\Bullet_List;
use Illuminate\Console\View\Components\Error;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\Exception_Handler as ExceptionHandlerContract;
use Illuminate\Contracts\Debug\Shouldnt_Report;
use Illuminate\Contracts\Foundation\Exception_Renderer;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model_Not_Found_Exception;
use Illuminate\Database\Multiple_Records_Found_Exception;
use Illuminate\Database\Record_Not_Found_Exception;
use Illuminate\Database\Records_Not_Found_Exception;
use Illuminate\Foundation\Exceptions\Renderer\Renderer;
use Illuminate\Http\Exceptions\Http_Response_Exception;
use Illuminate\Http\Redirect_Response;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\Backed_Enum_Case_Not_Found_Exception;
use Illuminate\Routing\Router;
use Illuminate\Session\Token_Mismatch_Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Lottery;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Reflects_Closures;
use Illuminate\Support\View_Error_Bag;
use Illuminate\Validation\Validation_Exception;
use InvalidArgumentException;
use Psr\Log\Logger_Interface;
use Psr\Log\Log_Level;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Exception\Command_Not_Found_Exception;
use Symfony\Component\Error_Handler\Error_Renderer\Html_Error_Renderer;
use Symfony\Component\Http_Foundation\Exception\Request_Exception_Interface;
use Symfony\Component\Http_Foundation\Redirect_Response as SymfonyRedirectResponse;
use Symfony\Component\Http_Foundation\Response as SymfonyResponse;
use Symfony\Component\Http_Kernel\Exception\Access_Denied_Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Bad_Request_Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Http_Exception_Interface;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Throwable;
use WeakMap;
class Handler implements Exception_Handler_Contract
{
    use Reflects_Closures;
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dont_report = [];
    /**
     * The callbacks that inspect exceptions to determine if they should be reported.
     *
     * @var array
     */
    protected $dont_report_callbacks = [];
    /**
     * The callbacks that should be used during reporting.
     *
     * @var \Illuminate\Foundation\Exceptions\ReportableHandler[]
     */
    protected $report_callbacks = [];
    /**
     * A map of exceptions with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [];
    /**
     * The callbacks that should be used to throttle reportable exceptions.
     *
     * @var array
     */
    protected $throttle_callbacks = [];
    /**
     * The callbacks that should be used to build exception context data.
     *
     * @var array
     */
    protected $context_callbacks = [];
    /**
     * The callbacks that should be used during rendering.
     *
     * @var \Closure[]
     */
    protected $render_callbacks = [];
    /**
     * The callback that determines if the exception handler response should be JSON.
     *
     * @var callable|null
     */
    protected $should_render_json_when_callback;
    /**
     * The callback that prepares responses to be returned to the browser.
     *
     * @var callable|null
     */
    protected $finalize_response_callback;
    /**
     * The registered exception mappings.
     *
     * @var array<string, \Closure>
     */
    protected $exception_map = [];
    /**
     * Indicates that throttled keys should be hashed.
     *
     * @var bool
     */
    protected $hash_throttle_keys = true;
    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $internal_dont_report = [Authentication_Exception::class, Authorization_Exception::class, Backed_Enum_Case_Not_Found_Exception::class, Http_Exception::class, Http_Response_Exception::class, Model_Not_Found_Exception::class, Multiple_Records_Found_Exception::class, Record_Not_Found_Exception::class, Records_Not_Found_Exception::class, Request_Exception_Interface::class, Token_Mismatch_Exception::class, Validation_Exception::class];
    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dont_flash = ['current_password', 'password', 'password_confirmation'];
    /**
     * Indicates that an exception instance should only be reported once.
     *
     * @var bool
     */
    protected $without_duplicates = false;
    /**
     * The already reported exception map.
     *
     * @var \WeakMap
     */
    protected $reported_exception_map;
    /**
     * Create a new exception handler instance.
     */
    public function __construct(
        /**
         * The container implementation.
         */
        protected \Illuminate\Contracts\Container\Container $container
    )
    {
        $this->reported_exception_map = new WeakMap();
        $this->register();
    }
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
    }
    /**
     * Register a reportable callback.
     *
     * @return \Illuminate\Foundation\Exceptions\ReportableHandler
     */
    public function reportable(callable $report_using)
    {
        if (!$report_using instanceof Closure) {
            $report_using = Closure::from_callable($report_using);
        }
        return tap(new Reportable_Handler($report_using), function ($callback): void {
            $this->report_callbacks[] = $callback;
        });
    }
    /**
     * Register a renderable callback.
     *
     * @return $this
     */
    public function renderable(callable $render_using): static
    {
        if (!$render_using instanceof Closure) {
            $render_using = Closure::from_callable($render_using);
        }
        $this->render_callbacks[] = $render_using;
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
        if (is_string($to)) {
            $to = fn($exception): object => new $to('', 0, $exception);
        }
        if (is_callable($from) && is_null($to)) {
            $from = $this->first_closure_parameter_type($to = $from);
        }
        if (!is_string($from) || !$to instanceof Closure) {
            throw new InvalidArgumentException('Invalid exception mapping.');
        }
        $this->exception_map[$from] = $to;
        return $this;
    }
    /**
     * Indicate that the given exception type should not be reported.
     *
     * Alias of "ignore".
     *
     * @return $this
     */
    public function dont_report(array|string $exceptions): static
    {
        return $this->ignore($exceptions);
    }
    /**
     * Register a callback to determine if an exception should not be reported.
     *
     * @param  (callable(\Throwable): bool)  $dontReportWhen
     * @return $this
     */
    public function dont_report_when(callable $dont_report_when): static
    {
        if (!$dont_report_when instanceof Closure) {
            $dont_report_when = Closure::from_callable($dont_report_when);
        }
        $this->dont_report_callbacks[] = $dont_report_when;
        return $this;
    }
    /**
     * Indicate that the given exception type should not be reported.
     *
     * @return $this
     */
    public function ignore(array|string $exceptions): static
    {
        $exceptions = Arr::wrap($exceptions);
        $this->dont_report = array_values(array_unique(array_merge($this->dont_report, $exceptions)));
        return $this;
    }
    /**
     * Indicate that the given attributes should never be flashed to the session on validation errors.
     *
     * @return $this
     */
    public function dont_flash(array|string $attributes): static
    {
        $this->dont_flash = array_values(array_unique(array_merge($this->dont_flash, Arr::wrap($attributes))));
        return $this;
    }
    /**
     * Set the log level for the given exception type.
     *
     * @param  class-string<\Throwable>  $type
     * @param  \Psr\Log\LogLevel::*  $level
     * @return $this
     */
    public function level(string $type, $level): static
    {
        $this->levels[$type] = $level;
        return $this;
    }
    /**
     * Report or log an exception.
     *
     *
     * @throws \Throwable
     */
    public function report(Throwable $e): void
    {
        $e = $this->map_exception($e);
        if ($this->shouldnt_report($e)) {
            return;
        }
        $this->report_throwable($e);
    }
    /**
     * Reports error based on report method on exception or to logger.
     *
     *
     * @throws \Throwable
     */
    protected function report_throwable(Throwable $e): void
    {
        $this->reported_exception_map[$e] = true;
        if (Reflector::is_callable($report_callable = [$e, 'report']) && $this->container->call($report_callable) !== false) {
            return;
        }
        foreach ($this->report_callbacks as $report_callback) {
            if ($report_callback->handles($e) && $report_callback($e) === false) {
                return;
            }
        }
        try {
            $logger = $this->new_logger();
        } catch (Exception) {
            throw $e;
        }
        $level = $this->map_log_level($e);
        $context = $this->build_exception_context($e);
        method_exists($logger, $level) ? $logger->{$level}($e->get_message(), $context) : $logger->log($level, $e->get_message(), $context);
    }
    /**
     * Determine if the exception should be reported.
     */
    public function should_report(Throwable $e): bool
    {
        return !$this->shouldnt_report($e);
    }
    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @return bool
     */
    protected function shouldnt_report(Throwable $e)
    {
        if ($this->without_duplicates && ($this->reported_exception_map[$e] ?? false)) {
            return true;
        }
        if ($e instanceof Shouldnt_Report) {
            return true;
        }
        $dont_report = array_merge($this->dont_report, $this->internal_dont_report);
        if (!is_null(Arr::first($dont_report, fn($type): bool => $e instanceof $type))) {
            return true;
        }
        foreach ($this->dont_report_callbacks as $dont_report_callback) {
            if ($dont_report_callback($e) === true) {
                return true;
            }
        }
        return rescue(fn() => with($this->throttle($e), function ($throttle) use ($e): bool {
            if ($throttle instanceof Unlimited || $throttle === null) {
                return false;
            }
            if ($throttle instanceof Lottery) {
                return !$throttle($e);
            }
            return !$this->container->make(Rate_Limiter::class)->attempt(with($throttle->key ?: 'illuminate:foundation:exceptions:' . $e::class, fn($key): mixed => $this->hash_throttle_keys ? hash('xxh128', $key) : $key), $throttle->max_attempts, fn(): true => true, $throttle->decay_seconds);
        }), rescue: false, report: false);
    }
    /**
     * Throttle the given exception.
     *
     * @return \Illuminate\Support\Lottery|\Illuminate\Cache\RateLimiting\Limit|null
     */
    protected function throttle(Throwable $e)
    {
        foreach ($this->throttle_callbacks as $throttle_callback) {
            foreach ($this->first_closure_parameter_types($throttle_callback) as $type) {
                if (is_a($e, $type)) {
                    $response = $throttle_callback($e);
                    if (!is_null($response)) {
                        return $response;
                    }
                }
            }
        }
        return Limit::none();
    }
    /**
     * Specify the callback that should be used to throttle reportable exceptions.
     *
     * @return $this
     */
    public function throttle_using(callable $throttle_using): static
    {
        if (!$throttle_using instanceof Closure) {
            $throttle_using = Closure::from_callable($throttle_using);
        }
        $this->throttle_callbacks[] = $throttle_using;
        return $this;
    }
    /**
     * Remove the given exception class from the list of exceptions that should be ignored.
     *
     * @return $this
     */
    public function stop_ignoring(array|string $exceptions): static
    {
        $exceptions = Arr::wrap($exceptions);
        $this->dont_report = (new Collection($this->dont_report))->reject(fn($ignored): bool => in_array($ignored, $exceptions))->values()->all();
        $this->internal_dont_report = (new Collection($this->internal_dont_report))->reject(fn($ignored): bool => in_array($ignored, $exceptions))->values()->all();
        return $this;
    }
    /**
     * Create the context array for logging the given exception.
     */
    protected function build_exception_context(Throwable $e): array
    {
        return array_merge($this->exception_context($e), $this->context(), ['exception' => $e]);
    }
    /**
     * Get the default exception context variables for logging.
     *
     * @return array
     */
    protected function exception_context(Throwable $e)
    {
        $context = [];
        if (method_exists($e, 'context')) {
            $context = $e->context();
        }
        foreach ($this->context_callbacks as $callback) {
            $context = array_merge($context, $callback($e, $context));
        }
        return $context;
    }
    /**
     * Get the default context variables for logging.
     */
    protected function context(): array
    {
        try {
            return array_filter(['userId' => Auth::id()]);
        } catch (Throwable) {
            return [];
        }
    }
    /**
     * Register a closure that should be used to build exception context data.
     *
     * @return $this
     */
    public function build_context_using(Closure $context_callback): static
    {
        $this->context_callbacks[] = $context_callback;
        return $this;
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
        $e = $this->map_exception($e);
        if (method_exists($e, 'render') && $response = $e->render($request)) {
            return $this->finalize_rendered_response($request, Router::to_response($request, $response), $e);
        }
        if ($e instanceof Responsable) {
            return $this->finalize_rendered_response($request, $e->to_response($request), $e);
        }
        $e = $this->prepare_exception($e);
        if ($response = $this->render_via_callbacks($request, $e)) {
            return $this->finalize_rendered_response($request, $response, $e);
        }
        return $this->finalize_rendered_response($request, match (true) {
            $e instanceof Http_Response_Exception => $e->get_response(),
            $e instanceof Authentication_Exception => $this->unauthenticated($request, $e),
            $e instanceof Validation_Exception => $this->convert_validation_exception_to_response($e, $request),
            default => $this->render_exception_response($request, $e),
        }, $e);
    }
    /**
     * Prepare the final, rendered response to be returned to the browser.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function finalize_rendered_response($request, $response, Throwable $e)
    {
        return $this->finalize_response_callback ? call_user_func($this->finalize_response_callback, $response, $e, $request) : $response;
    }
    /**
     * Prepare the final, rendered response for an exception using the given callback.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function respond_using($callback): static
    {
        $this->finalize_response_callback = $callback;
        return $this;
    }
    /**
     * Prepare exception for rendering.
     *
     * @return \Throwable
     */
    protected function prepare_exception(Throwable $e): \Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception|\Symfony\Component\Http_Kernel\Exception\Http_Exception|\Symfony\Component\Http_Kernel\Exception\Access_Denied_Http_Exception|\Symfony\Component\Http_Kernel\Exception\Bad_Request_Http_Exception|\Throwable
    {
        return match (true) {
            $e instanceof Backed_Enum_Case_Not_Found_Exception => new Not_Found_Http_Exception($e->get_message(), $e),
            $e instanceof Model_Not_Found_Exception => new Not_Found_Http_Exception($e->get_message(), $e),
            $e instanceof Authorization_Exception && $e->has_status() => new Http_Exception($e->status(), $e->response()?->message() ?: Response::$status_texts[$e->status()] ?? 'Whoops, looks like something went wrong.', $e),
            $e instanceof Authorization_Exception && !$e->has_status() => new Access_Denied_Http_Exception($e->get_message(), $e),
            $e instanceof Token_Mismatch_Exception => new Http_Exception(419, $e->get_message(), $e),
            $e instanceof Request_Exception_Interface => new Bad_Request_Http_Exception('Bad request.', $e),
            $e instanceof Record_Not_Found_Exception => new Not_Found_Http_Exception('Not found.', $e),
            $e instanceof Records_Not_Found_Exception => new Not_Found_Http_Exception('Not found.', $e),
            default => $e,
        };
    }
    /**
     * Map the exception using a registered mapper if possible.
     *
     * @return \Throwable
     */
    protected function map_exception(Throwable $e)
    {
        if (method_exists($e, 'getInnerException') && ($inner = $e->get_inner_exception()) instanceof Throwable) {
            return $inner;
        }
        foreach ($this->exception_map as $class => $mapper) {
            if (is_a($e, $class)) {
                return $mapper($e);
            }
        }
        return $e;
    }
    /**
     * Try to render a response from request and exception via render callbacks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     * @throws \ReflectionException
     */
    protected function render_via_callbacks($request, Throwable $e)
    {
        foreach ($this->render_callbacks as $render_callback) {
            foreach ($this->first_closure_parameter_types($render_callback) as $type) {
                if (is_a($e, $type)) {
                    $response = $render_callback($e, $request);
                    if (!is_null($response)) {
                        return $response;
                    }
                }
            }
        }
    }
    /**
     * Render a default exception response if any.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function render_exception_response($request, Throwable $e)
    {
        return $this->should_return_json($request, $e) ? $this->prepare_json_response($request, $e) : $this->prepare_response($request, $e);
    }
    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function unauthenticated($request, Authentication_Exception $exception)
    {
        return $this->should_return_json($request, $exception) ? response()->json(['message' => $exception->get_message()], 401) : redirect()->guest($exception->redirect_to($request) ?? route('login'));
    }
    /**
     * Create a response object from the given validation exception.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convert_validation_exception_to_response(Validation_Exception $e, $request)
    {
        if ($e->response) {
            return $e->response;
        }
        return $this->should_return_json($request, $e) ? $this->invalid_json($request, $e) : $this->invalid($request, $e);
    }
    /**
     * Convert a validation exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function invalid($request, Validation_Exception $exception)
    {
        return redirect($exception->redirect_to ?? url()->previous())->with_input(Arr::except($request->input(), $this->dont_flash))->with_errors($exception->errors(), $request->input('_error_bag', $exception->error_bag));
    }
    /**
     * Convert a validation exception into a JSON response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function invalid_json($request, Validation_Exception $exception)
    {
        return response()->json(['message' => $exception->get_message(), 'errors' => $exception->errors()], $exception->status);
    }
    /**
     * Determine if the exception handler response should be JSON.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function should_return_json($request, Throwable $e)
    {
        return $this->should_render_json_when_callback ? call_user_func($this->should_render_json_when_callback, $request, $e) : $request->expects_json();
    }
    /**
     * Register the callable that determines if the exception handler response should be JSON.
     *
     * @param  callable(\Illuminate\Http\Request $request, \Throwable): bool  $callback
     * @return $this
     */
    public function should_render_json_when($callback): static
    {
        $this->should_render_json_when_callback = $callback;
        return $this;
    }
    /**
     * Prepare a response for the given exception.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function prepare_response($request, Throwable $e)
    {
        if (!$this->is_http_exception($e) && config('app.debug')) {
            return $this->to_illuminate_response($this->convert_exception_to_response($e), $e)->prepare($request);
        }
        if (!$this->is_http_exception($e)) {
            $e = new Http_Exception(500, $e->get_message(), $e);
        }
        return $this->to_illuminate_response($this->render_http_exception($e), $e)->prepare($request);
    }
    /**
     * Create a Symfony response for the given exception.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convert_exception_to_response(Throwable $e)
    {
        return new Symfony_Response($this->render_exception_content($e), $this->is_http_exception($e) ? $e->get_status_code() : 500, $this->is_http_exception($e) ? $e->get_headers() : []);
    }
    /**
     * Get the response content for the given exception.
     *
     * @return string
     */
    protected function render_exception_content(Throwable $e)
    {
        try {
            if (!config('app.debug')) {
                return $this->render_exception_with_symfony($e, config('app.debug'));
            }
            if (app()->has(Exception_Renderer::class)) {
                return $this->render_exception_with_custom_renderer($e);
            }
            if ($this->container->bound(Renderer::class)) {
                return $this->container->make(Renderer::class)->render(request(), $e);
            }
            return $this->render_exception_with_symfony($e, config('app.debug'));
        } catch (Throwable $e) {
            return $this->render_exception_with_symfony($e, config('app.debug'));
        }
    }
    /**
     * Render an exception to a string using the registered `ExceptionRenderer`.
     *
     * @return string
     */
    protected function render_exception_with_custom_renderer(Throwable $e)
    {
        return app(Exception_Renderer::class)->render($e);
    }
    /**
     * Render an exception to a string using Symfony.
     *
     * @param  bool  $debug
     * @return string
     */
    protected function render_exception_with_symfony(Throwable $e, $debug)
    {
        $renderer = new Html_Error_Renderer($debug);
        return $renderer->render($e)->get_as_string();
    }
    /**
     * Render the given HttpException.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function render_http_exception(Http_Exception_Interface $e)
    {
        $this->register_error_view_paths();
        if ($view = $this->get_http_exception_view($e)) {
            try {
                return response()->view($view, ['errors' => new View_Error_Bag(), 'exception' => $e], $e->get_status_code(), $e->get_headers());
            } catch (Throwable $t) {
                config('app.debug') && throw $t;
                $this->report($t);
            }
        }
        return $this->convert_exception_to_response($e);
    }
    /**
     * Register the error template hint paths.
     *
     * @return void
     */
    protected function register_error_view_paths()
    {
        (new Register_Error_View_Paths())();
    }
    /**
     * Get the view used to render HTTP exceptions.
     */
    protected function get_http_exception_view(Http_Exception_Interface $e): ?string
    {
        $view = 'errors::' . $e->get_status_code();
        if (view()->exists($view)) {
            return $view;
        }
        $view = substr($view, 0, -2) . 'xx';
        if (view()->exists($view)) {
            return $view;
        }
        return null;
    }
    /**
     * Map the given exception into an Illuminate response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function to_illuminate_response($response, Throwable $e)
    {
        if ($response instanceof Symfony_Redirect_Response) {
            $response = new Redirect_Response($response->get_target_url(), $response->get_status_code(), $response->headers->all());
        } else {
            $response = response($response->get_content(), $response->get_status_code(), $response->headers->all());
        }
        return $response->with_exception($e);
    }
    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function prepare_json_response($request, Throwable $e)
    {
        return response()->json($this->convert_exception_to_array($e), $this->is_http_exception($e) ? $e->get_status_code() : 500, $this->is_http_exception($e) ? $e->get_headers() : [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    /**
     * Convert the given exception to an array.
     */
    protected function convert_exception_to_array(Throwable $e): array
    {
        return config('app.debug') ? ['message' => $e->get_message(), 'exception' => $e::class, 'file' => $e->get_file(), 'line' => $e->get_line(), 'trace' => (new Collection($e->get_trace()))->map(fn(array $trace): array => Arr::except($trace, ['args']))->all()] : ['message' => $this->is_http_exception($e) ? $e->get_message() : 'Server Error'];
    }
    /**
     * Render an exception to the console.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     *
     * @internal This method is not meant to be used or overwritten outside the framework.
     */
    public function render_for_console($output, Throwable $e): void
    {
        if ($e instanceof Command_Not_Found_Exception) {
            $message = Str::of($e->get_message())->explode('.')->first();
            if (!empty($alternatives = $e->get_alternatives())) {
                $message .= '. Did you mean one of these?';
                (new Error($output))->render($message);
                (new Bullet_List($output))->render($alternatives);
                $output->writeln('');
            } else {
                (new Error($output))->render($message);
            }
            return;
        }
        (new Console_Application())->render_throwable($e, $output);
    }
    /**
     * Do not report duplicate exceptions.
     *
     * @return $this
     */
    public function dont_report_duplicates(): static
    {
        $this->without_duplicates = true;
        return $this;
    }
    /**
     * Determine if the given exception is an HTTP exception.
     */
    protected function is_http_exception(Throwable $e): bool
    {
        return $e instanceof Http_Exception_Interface;
    }
    /**
     * Map the exception to a log level.
     *
     * @return \Psr\Log\LogLevel::*
     */
    protected function map_log_level(Throwable $e)
    {
        return Arr::first($this->levels, fn($level, $type): bool => $e instanceof $type, Log_Level::ERROR);
    }
    /**
     * Create a new logger instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function new_logger()
    {
        return $this->container->make(Logger_Interface::class);
    }
}