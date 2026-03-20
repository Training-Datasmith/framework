<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Concerns;

use Illuminate\Support\Str;
use Throwable;
trait Resolves_Dump_Source
{
    /**
     * All of the href formats for common editors.
     *
     * @var array<string, string>
     */
    protected $editor_hrefs = ['antigravity' => 'antigravity://file/{file}:{line}', 'atom' => 'atom://core/open/file?filename={file}&line={line}', 'cursor' => 'cursor://file/{file}:{line}', 'emacs' => 'emacs://open?url=file://{file}&line={line}', 'fleet' => 'fleet://open?file={file}&line={line}', 'idea' => 'idea://open?file={file}&line={line}', 'kiro' => 'kiro://file/{file}:{line}', 'macvim' => 'mvim://open/?url=file://{file}&line={line}', 'neovim' => 'nvim://open?url=file://{file}&line={line}', 'netbeans' => 'netbeans://open/?f={file}:{line}', 'nova' => 'nova://core/open/file?filename={file}&line={line}', 'phpstorm' => 'phpstorm://open?file={file}&line={line}', 'sublime' => 'subl://open?url=file://{file}&line={line}', 'textmate' => 'txmt://open?url=file://{file}&line={line}', 'trae' => 'trae://file/{file}:{line}', 'vscode' => 'vscode://file/{file}:{line}', 'vscode-insiders' => 'vscode-insiders://file/{file}:{line}', 'vscode-insiders-remote' => 'vscode-insiders://vscode-remote/{file}:{line}', 'vscode-remote' => 'vscode://vscode-remote/{file}:{line}', 'vscodium' => 'vscodium://file/{file}:{line}', 'windsurf' => 'windsurf://file/{file}:{line}', 'xdebug' => 'xdebug://{file}@{line}', 'zed' => 'zed://file/{file}:{line}'];
    /**
     * Files that require special trace handling and their levels.
     *
     * @var array<string, int>
     */
    protected static $adjustable_traces = ['symfony/var-dumper/Resources/functions/dump.php' => 1, 'Illuminate/Collections/Traits/EnumeratesValues.php' => 4];
    /**
     * The source resolver.
     *
     * @var (callable(): (array{0: string, 1: string, 2: int|null}|null))|null|false
     */
    protected static $dump_source_resolver;
    /**
     * Resolve the source of the dump call.
     *
     * @return array{0: string, 1: string, 2: int|null}|null
     */
    public function resolve_dump_source()
    {
        if (static::$dump_source_resolver === false) {
            return null;
        }
        if (static::$dump_source_resolver) {
            return call_user_func(static::$dump_source_resolver);
        }
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        $source_key = null;
        foreach ($trace as $trace_key => $trace_file) {
            if (!isset($trace_file['file'])) {
                continue;
            }
            foreach (self::$adjustable_traces as $name => $key) {
                if (str_ends_with($trace_file['file'], str_replace('/', DIRECTORY_SEPARATOR, $name))) {
                    $source_key = $trace_key + $key;
                    break;
                }
            }
            if (!is_null($source_key)) {
                break;
            }
        }
        if (is_null($source_key)) {
            return;
        }
        $file = $trace[$source_key]['file'] ?? null;
        $line = $trace[$source_key]['line'] ?? null;
        if (is_null($file) || is_null($line)) {
            return;
        }
        $relative_file = $file;
        if ($this->is_compiled_view_file($file)) {
            $file = $this->get_original_file_for_compiled_view($file);
            $line = null;
        }
        if (str_starts_with((string) $file, $this->base_path)) {
            $relative_file = substr((string) $file, strlen($this->base_path) + 1);
        }
        return [$file, $relative_file, $line];
    }
    /**
     * Determine if the given file is a view compiled.
     *
     * @param  string  $file
     */
    protected function is_compiled_view_file($file): bool
    {
        return str_starts_with($file, $this->compiled_view_path) && str_ends_with($file, '.php');
    }
    /**
     * Get the original view compiled file by the given compiled file.
     *
     * @param  string  $file
     * @return string
     */
    protected function get_original_file_for_compiled_view($file)
    {
        preg_match('/\/\*\*PATH\s(.*)\sENDPATH/', file_get_contents($file), $matches);
        return $matches[1] ?? $file;
    }
    /**
     * Resolve the source href, if possible.
     *
     * @param  string  $file
     * @param  int|null  $line
     * @return string|null
     */
    protected function resolve_source_href($file, $line)
    {
        try {
            $editor = config('app.editor');
        } catch (Throwable) {
            // ..
        }
        if (!isset($editor)) {
            return;
        }
        $href = is_array($editor) && isset($editor['href']) ? $editor['href'] : $this->editor_hrefs[$editor['name'] ?? $editor] ?? sprintf('%s://open?file={file}&line={line}', $editor['name'] ?? $editor);
        $base_path = $editor['base_path'] ?? false;
        if ($base_path !== false) {
            $file = Str::replace_start($this->base_path, $base_path, $file);
        }
        return str_replace(['{file}', '{line}'], [$file, is_null($line) ? 1 : $line], $href);
    }
    /**
     * Set the resolver that resolves the source of the dump call.
     *
     * @param  (callable(): (array{0: string, 1: string, 2: int|null}|null))|null  $callable
     */
    public static function resolve_dump_source_using($callable): void
    {
        static::$dump_source_resolver = $callable;
    }
    /**
     * Don't include the location / file of the dump in dumps.
     */
    public static function dont_include_source(): void
    {
        static::$dump_source_resolver = false;
    }
}