<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions\Renderer;

use Closure;
use Composer\Autoload\Class_Loader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bootstrap\Handle_Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\Error_Handler\Exception\Flatten_Exception;
class Exception
{
    /**
     * The "flattened" exception instance.
     *
     * @var \Symfony\Component\ErrorHandler\Exception\FlattenException
     */
    protected $exception;
    /**
     * Creates a new exception renderer instance.
     */
    public function __construct(
        Flatten_Exception $exception,
        /**
         * The current request instance.
         */
        protected \Illuminate\Http\Request $request,
        /**
         * The exception listener instance.
         */
        protected \Illuminate\Foundation\Exceptions\Renderer\Listener $listener,
        /**
         * The application's base path.
         */
        protected string $base_path
    )
    {
        $this->exception = $exception;
    }
    /**
     * Get the exception title.
     *
     * @return string
     */
    public function title()
    {
        return $this->exception->get_status_text();
    }
    /**
     * Get the exception message.
     *
     * @return string
     */
    public function message()
    {
        return $this->exception->get_message();
    }
    /**
     * Get the exception class name.
     *
     * @return string
     */
    public function class()
    {
        return $this->exception->get_class();
    }
    /**
     * Get the exception code.
     *
     * @return int|string
     */
    public function code()
    {
        return $this->exception->get_code();
    }
    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function http_status_code()
    {
        return $this->exception->get_status_code();
    }
    /**
     * Get the exception's frames.
     *
     * @return \Illuminate\Support\Collection<int, Frame>
     */
    public function frames()
    {
        return once(function (): \Illuminate\Support\Collection {
            $class_map = array_map(fn(string $path): string => (string) realpath($path), array_values(Class_Loader::get_registered_loaders())[0]->get_class_map());
            $trace = $this->exception->get_trace();
            if (count($trace) > 1 && empty($trace[0]['class']) && empty($trace[0]['function'])) {
                $trace[0]['class'] = $trace[1]['class'] ?? '';
                $trace[0]['type'] = $trace[1]['type'] ?? '';
                $trace[0]['function'] = $trace[1]['function'] ?? '';
                $trace[0]['args'] = $trace[1]['args'] ?? [];
            }
            $trace = array_values(array_filter($trace, fn(array $trace): bool => isset($trace['file'])));
            if (($trace[1]['class'] ?? '') === Handle_Exceptions::class) {
                array_shift($trace);
                array_shift($trace);
            }
            $frames = [];
            $previous_frame = null;
            foreach (array_reverse($trace) as $frame_data) {
                $frame = new Frame($this->exception, $class_map, $frame_data, $this->base_path, $previous_frame);
                $frames[] = $frame;
                $previous_frame = $frame;
            }
            $frames = array_reverse($frames);
            foreach ($frames as $frame) {
                if (!$frame->is_from_vendor()) {
                    $frame->mark_as_main();
                    break;
                }
            }
            return new Collection($frames);
        });
    }
    /**
     * Get the exception's frames grouped by vendor status.
     *
     * @return array<int, array{is_vendor: bool, frames: array<int, Frame>}>
     */
    public function frame_groups(): array
    {
        $groups = [];
        foreach ($this->frames() as $frame) {
            $is_vendor = $frame->is_from_vendor();
            if (empty($groups) || $groups[array_key_last($groups)]['is_vendor'] !== $is_vendor) {
                $groups[] = ['is_vendor' => $is_vendor, 'frames' => []];
            }
            $groups[array_key_last($groups)]['frames'][] = $frame;
        }
        return $groups;
    }
    /**
     * Get the exception's request instance.
     */
    public function request(): \Illuminate\Http\Request
    {
        return $this->request;
    }
    /**
     * Get the request's headers.
     *
     * @return array<string, string>
     */
    public function request_headers(): array
    {
        return array_map(fn(array $header): string => implode(', ', $header), $this->request()->headers->all());
    }
    /**
     * Get the request's body parameters.
     */
    public function request_body(): ?string
    {
        if (empty($payload = $this->request()->all())) {
            return null;
        }
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return str_replace('\\', '', $json);
    }
    /**
     * Get the application's route context.
     *
     * @return array<string, string>
     */
    public function application_route_context(): array
    {
        $route = $this->request()->route();
        return $route ? array_filter(['controller' => $route->get_action_name(), 'route name' => $route->get_name() ?: null, 'middleware' => implode(', ', array_map(fn($middleware) => $middleware instanceof Closure ? 'Closure' : $middleware, $route->gather_middleware()))]) : [];
    }
    /**
     * Get the application's route parameters context.
     *
     * @return array<string, mixed>|null
     */
    public function application_route_parameters_context()
    {
        $parameters = $this->request()->route()?->parameters();
        return $parameters ? json_encode(array_map(fn($value) => $value instanceof Model ? $value->without_relations() : $value, $parameters), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null;
    }
    /**
     * Get the application's SQL queries.
     *
     * @return array<int, array{connectionName: string, time: float, sql: string}>
     */
    public function application_queries(): array
    {
        return array_map(function (array $query): array {
            $sql = $query['sql'];
            foreach ($query['bindings'] as $binding) {
                $sql = match (gettype($binding)) {
                    'integer', 'double' => preg_replace('/\?/', $binding, $sql, 1),
                    'NULL' => preg_replace('/\?/', 'NULL', $sql, 1),
                    default => preg_replace('/\?/', "'{$binding}'", $sql, 1),
                };
            }
            return ['connectionName' => $query['connectionName'], 'time' => $query['time'], 'sql' => $sql];
        }, $this->listener->queries());
    }
}