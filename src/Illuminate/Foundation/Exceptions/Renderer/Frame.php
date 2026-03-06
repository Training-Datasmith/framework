<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Exceptions\Renderer;

use function Illuminate\Filesystem\join_paths;

use Illuminate\Foundation\Concerns\ResolvesDumpSource;

use Symfony\Component\ErrorHandler\Exception\FlattenException;

class Frame
{
    use ResolvesDumpSource;

    /**
     * The "flattened" exception instance.
     *
     * @var \Symfony\Component\ErrorHandler\Exception\FlattenException
     */
    protected $exception;

    /**
     * Whether this frame is the main (first non-vendor) frame.
     *
     * @var bool
     */
    protected $isMain = false;

    /**
     * Create a new frame instance.
     *
     * @param  array<string, string>  $classMap
     * @param  array{file: string, line: int, class?: string, type?: string, function?: string, args?: array}  $frame
     */
    public function __construct(FlattenException $exception, /**
     * The application's class map.
     */
        protected array $classMap, /**
     * The frame's raw data from the "flattened" exception.
     */
        protected array $frame, /**
     * The application's base path.
     */
        protected string $basePath, /**
     * The previous frame.
     */
        protected ?\Illuminate\Foundation\Exceptions\Renderer\Frame $previous = null)
    {
        $this->exception = $exception;
    }

    /**
     * Get the frame's source / origin.
     *
     * @return string
     */
    public function source(): string|array
    {
        return match (true) {
            is_string($this->class()) => $this->class(),
            default => $this->file(),
        };
    }

    /**
     * Get the frame's editor link.
     *
     * @return string
     */
    public function editorHref()
    {
        return $this->resolveSourceHref($this->frame['file'], $this->line());
    }

    /**
     * Get the frame's class, if any.
     *
     * @return string|null
     */
    public function class(): string|int|null
    {
        if (! empty($this->frame['class'])) {
            return $this->frame['class'];
        }

        $class = array_search((string) realpath($this->frame['file']), $this->classMap, true);

        return $class === false ? null : $class;
    }

    /**
     * Get the frame's file.
     *
     * @return string
     */
    public function file(): string|array
    {
        return match (true) {
            ! isset($this->frame['file']) => '[internal function]',
            ! is_string($this->frame['file']) => '[unknown file]',
            default => str_replace($this->basePath.DIRECTORY_SEPARATOR, '', $this->frame['file']),
        };
    }

    /**
     * Get the frame's line number.
     *
     * @return int
     */
    public function line()
    {
        if (! is_file($this->frame['file']) || ! is_readable($this->frame['file'])) {
            return 0;
        }

        $maxLines = count(file($this->frame['file']) ?: []);

        return $this->frame['line'] > $maxLines ? 1 : $this->frame['line'];
    }

    /**
     * Get the frame's function operator.
     *
     * @return '::'|'->'|''
     */
    public function operator()
    {
        return $this->frame['type'] ?? '';
    }

    /**
     * Get the frame's function or method.
     *
     * @return string
     */
    public function callable()
    {
        return match (true) {
            ! empty($this->frame['function']) => $this->frame['function'],
            default => 'throw',
        };
    }

    /**
     * Get the frame's arguments.
     */
    public function args(): array
    {
        if (! isset($this->frame['args']) || ! is_array($this->frame['args']) || count($this->frame['args']) === 0) {
            return [];
        }

        return array_map(function ($argument) {
            [$key, $value] = $argument;

            return match ($key) {
                'object' => "{$key}({$value})",
                default => $key,
            };
        }, $this->frame['args']);
    }

    /**
     * Get the frame's code snippet.
     */
    public function snippet(): string
    {
        if (! is_file($this->frame['file']) || ! is_readable($this->frame['file'])) {
            return '';
        }

        $contents = file($this->frame['file']) ?: [];

        $start = max($this->line() - 6, 0);

        $length = 8 * 2 + 1;

        return implode('', array_slice($contents, $start, $length));
    }

    /**
     * Determine if the frame is from the vendor directory.
     */
    public function isFromVendor(): bool
    {
        return ! str_starts_with($this->frame['file'], $this->basePath)
            || str_starts_with($this->frame['file'], join_paths($this->basePath, 'vendor'));
    }

    /**
     * Get the previous frame.
     */
    public function previous(): ?\Illuminate\Foundation\Exceptions\Renderer\Frame
    {
        return $this->previous;
    }

    /**
     * Mark this frame as the main frame.
     */
    public function markAsMain(): void
    {
        $this->isMain = true;
    }

    /**
     * Determine if this is the main frame.
     *
     * @return bool
     */
    public function isMain()
    {
        return $this->isMain;
    }
}
