<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Exceptions\Renderer\Mappers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\View\View_Exception;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Error_Handler\Exception\Flatten_Exception;
use Throwable;
/*
 * This file contains parts of https://github.com/spatie/laravel-ignition.
 *
 * (c) Spatie <info@spatie.be>
 *
 * For the full copyright and license information, please review its LICENSE:
 *
 * The MIT License (MIT)
 *
 * Copyright (c) Spatie <info@spatie.be>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
class Blade_Mapper
{
    /**
     * Create a new Blade mapper instance.
     */
    public function __construct(
        /**
         * The view factory instance.
         */
        protected \Illuminate\Contracts\View\Factory $factory,
        /**
         * The Blade compiler instance.
         */
        protected \Illuminate\View\Compilers\Blade_Compiler $blade_compiler
    )
    {
    }
    /**
     * Map cached view paths to their original paths.
     *
     * @return \Symfony\Component\ErrorHandler\Exception\FlattenException
     */
    public function map(Flatten_Exception $exception)
    {
        while ($exception->get_class() === View_Exception::class) {
            if (($previous = $exception->get_previous()) === null) {
                break;
            }
            $exception = $previous;
        }
        $trace = (new Collection($exception->get_trace()))->map(function (array $frame): array {
            if ($original_path = $this->find_compiled_view((string) Arr::get($frame, 'file', ''))) {
                $frame['file'] = $original_path;
                $frame['line'] = $this->detect_line_number($frame['file'], $frame['line']);
            }
            return $frame;
        })->to_array();
        return tap($exception, fn() => (fn() => $this->trace = $trace)->call($exception));
    }
    /**
     * Find the compiled view file for the given compiled path.
     *
     * @return string|null
     */
    protected function find_compiled_view(string $compiled_path)
    {
        return once(fn(): array => $this->get_known_paths())[$compiled_path] ?? null;
    }
    /**
     * Get the list of known paths from the compiler engine.
     *
     * @return array<string, string>
     */
    protected function get_known_paths(): array
    {
        $compiler_engine_reflection = new ReflectionClass($blade_compiler_engine = $this->factory->get_engine_resolver()->resolve('blade'));
        if (!$compiler_engine_reflection->has_property('lastCompiled') && $compiler_engine_reflection->has_property('engine')) {
            $compiler_engine = $compiler_engine_reflection->get_property('engine');
            $compiler_engine = $compiler_engine->get_value($blade_compiler_engine);
            $last_compiled = new ReflectionProperty($compiler_engine, 'lastCompiled');
            $last_compiled = $last_compiled->get_value($compiler_engine);
        } else {
            $last_compiled = $compiler_engine_reflection->get_property('lastCompiled');
            $last_compiled = $last_compiled->get_value($blade_compiler_engine);
        }
        $known_paths = [];
        foreach ($last_compiled as $last_compiled_path) {
            $compiled_path = $blade_compiler_engine->get_compiler()->get_compiled_path($last_compiled_path);
            $known_paths[realpath($compiled_path ?? $last_compiled_path)] = realpath($last_compiled_path);
        }
        return $known_paths;
    }
    /**
     * Filter out the view data that should not be shown in the exception report.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function filter_view_data(array $data): array
    {
        return array_filter($data, function ($value, $key): bool {
            if ($key === 'app') {
                return !$value instanceof Application;
            }
            return $key !== '__env';
        }, ARRAY_FILTER_USE_BOTH);
    }
    /**
     * Detect the line number in the original blade file.
     */
    protected function detect_line_number(string $filename, int $compiled_line_number): int
    {
        $map = $this->compile_sourcemap((string) file_get_contents($filename));
        return $this->find_closest_line_number_mapping($map, $compiled_line_number);
    }
    /**
     * Compile the source map for the given blade file.
     *
     * @return string
     */
    protected function compile_sourcemap(string $value)
    {
        try {
            $value = $this->add_echo_line_numbers($value);
            $value = $this->add_statement_line_numbers($value);
            $value = $this->add_blade_component_line_numbers($value);
            $value = $this->blade_compiler->compile_string($value);
            return $this->trim_empty_lines($value);
        } catch (Throwable $e) {
            report($e);
            return $value;
        }
    }
    /**
     * Add line numbers to echo statements.
     */
    protected function add_echo_line_numbers(string $value): string
    {
        $echo_pairs = [['{{', '}}'], ['{{{', '}}}'], ['{!!', '!!}']];
        foreach ($echo_pairs as $pair) {
            // Matches {{ $value }}, {!! $value !!} and  {{{ $value }}} depending on $pair
            $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $pair[0], $pair[1]);
            if (preg_match_all($pattern, $value, $matches, PREG_OFFSET_CAPTURE)) {
                foreach (array_reverse($matches[0]) as $match) {
                    $position = mb_strlen(substr($value, 0, $match[1]));
                    $value = $this->insert_line_number_at_position($position, $value);
                }
            }
        }
        return $value;
    }
    /**
     * Add line numbers to blade statements.
     */
    protected function add_statement_line_numbers(string $value): string
    {
        $should_insert_line_numbers = preg_match_all('/\B@(@?\w+(?:::\w+)?)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', $value, $matches, PREG_OFFSET_CAPTURE);
        if ($should_insert_line_numbers) {
            foreach (array_reverse($matches[0]) as $match) {
                $position = mb_strlen(substr($value, 0, $match[1]));
                $value = $this->insert_line_number_at_position($position, $value);
            }
        }
        return $value;
    }
    /**
     * Add line numbers to blade components.
     */
    protected function add_blade_component_line_numbers(string $value): string
    {
        $should_insert_line_numbers = preg_match_all('/<\s*x[-:]([\w\-:.]*)/mx', $value, $matches, PREG_OFFSET_CAPTURE);
        if ($should_insert_line_numbers) {
            foreach (array_reverse($matches[0]) as $match) {
                $position = mb_strlen(substr($value, 0, $match[1]));
                $value = $this->insert_line_number_at_position($position, $value);
            }
        }
        return $value;
    }
    /**
     * Insert a line number at the given position.
     */
    protected function insert_line_number_at_position(int $position, string $value): string
    {
        $before = mb_substr($value, 0, $position);
        $line_number = count(explode("\n", $before));
        return mb_substr($value, 0, $position) . "|---LINE:{$line_number}---|" . mb_substr($value, $position);
    }
    /**
     * Trim empty lines from the given value.
     */
    protected function trim_empty_lines(string $value): string
    {
        $value = preg_replace('/^\|---LINE:([0-9]+)---\|$/m', '', $value);
        return ltrim((string) $value, PHP_EOL);
    }
    /**
     * Find the closest line number mapping in the given source map.
     */
    protected function find_closest_line_number_mapping(string $map, int $compiled_line_number): int
    {
        $map = explode("\n", $map);
        $max_distance = 20;
        $pattern = '/\|---LINE:(?P<line>[0-9]+)---\|/m';
        $line_number_to_check = $compiled_line_number - 1;
        while (true) {
            if ($line_number_to_check < $compiled_line_number - $max_distance) {
                return min($compiled_line_number, count($map));
            }
            if (preg_match($pattern, $map[$line_number_to_check] ?? '', $matches)) {
                return (int) $matches['line'];
            }
            $line_number_to_check--;
        }
    }
}