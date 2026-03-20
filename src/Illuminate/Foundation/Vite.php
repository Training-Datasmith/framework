<?php

declare (strict_types=1);
namespace Illuminate\Foundation;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Html_String;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
class Vite implements Htmlable
{
    use Macroable;
    /**
     * The Content Security Policy nonce to apply to all generated tags.
     *
     * @var string|null
     */
    protected $nonce;
    /**
     * The key to check for integrity hashes within the manifest.
     *
     * @var string|false
     */
    protected $integrity_key = 'integrity';
    /**
     * The configured entry points.
     *
     * @var array
     */
    protected $entry_points = [];
    /**
     * The path to the "hot" file.
     *
     * @var string|null
     */
    protected $hot_file;
    /**
     * The path to the build directory.
     *
     * @var string
     */
    protected $build_directory = 'build';
    /**
     * The name of the manifest file.
     *
     * @var string
     */
    protected $manifest_filename = 'manifest.json';
    /**
     * The custom asset path resolver.
     *
     * @var callable|null
     */
    protected $asset_path_resolver;
    /**
     * The script tag attributes resolvers.
     *
     * @var array
     */
    protected $script_tag_attributes_resolvers = [];
    /**
     * The style tag attributes resolvers.
     *
     * @var array
     */
    protected $style_tag_attributes_resolvers = [];
    /**
     * The preload tag attributes resolvers.
     *
     * @var array
     */
    protected $preload_tag_attributes_resolvers = [];
    /**
     * The preloaded assets.
     *
     * @var array
     */
    protected $preloaded_assets = [];
    /**
     * The cached manifest files.
     *
     * @var array
     */
    protected static $manifests = [];
    /**
     * The prefetching strategy to use.
     *
     * @var null|'waterfall'|'aggressive'
     */
    protected $prefetch_strategy;
    /**
     * The number of assets to load concurrently when using the "waterfall" strategy.
     *
     * @var int
     */
    protected $prefetch_concurrently = 3;
    /**
     * The name of the event that should trigger prefetching. The event must be dispatched on the `window`.
     *
     * @var string
     */
    protected $prefetch_event = 'load';
    /**
     * Get the preloaded assets.
     *
     * @return array
     */
    public function preloaded_assets()
    {
        return $this->preloaded_assets;
    }
    /**
     * Get the Content Security Policy nonce applied to all generated tags.
     *
     * @return string|null
     */
    public function csp_nonce()
    {
        return $this->nonce;
    }
    /**
     * Generate or set a Content Security Policy nonce to apply to all generated tags.
     *
     * @param  string|null  $nonce
     * @return string
     */
    public function use_csp_nonce($nonce = null)
    {
        return $this->nonce = $nonce ?? Str::random(40);
    }
    /**
     * Use the given key to detect integrity hashes in the manifest.
     *
     * @param  string|false  $key
     * @return $this
     */
    public function use_integrity_key($key): static
    {
        $this->integrity_key = $key;
        return $this;
    }
    /**
     * Set the Vite entry points.
     *
     * @param  array  $entryPoints
     * @return $this
     */
    public function with_entry_points($entry_points): static
    {
        $this->entry_points = $entry_points;
        return $this;
    }
    /**
     * Merge additional Vite entry points with the current set.
     *
     * @param  array  $entryPoints
     * @return $this
     */
    public function merge_entry_points($entry_points): static
    {
        return $this->with_entry_points(array_unique([...$this->entry_points, ...$entry_points]));
    }
    /**
     * Set the filename for the manifest file.
     *
     * @param  string  $filename
     * @return $this
     */
    public function use_manifest_filename($filename): static
    {
        $this->manifest_filename = $filename;
        return $this;
    }
    /**
     * Resolve asset paths using the provided resolver.
     *
     * @param  callable|null  $resolver
     * @return $this
     */
    public function create_asset_paths_using($resolver): static
    {
        $this->asset_path_resolver = $resolver;
        return $this;
    }
    /**
     * Get the Vite "hot" file path.
     *
     * @return string
     */
    public function hot_file()
    {
        return $this->hot_file ?? public_path('/hot');
    }
    /**
     * Set the Vite "hot" file path.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_hot_file($path): static
    {
        $this->hot_file = $path;
        return $this;
    }
    /**
     * Set the Vite build directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function use_build_directory($path): static
    {
        $this->build_directory = $path;
        return $this;
    }
    /**
     * Use the given callback to resolve attributes for script tags.
     *
     * @param  (callable(string, string, ?array, ?array): array)|array  $attributes
     * @return $this
     */
    public function use_script_tag_attributes($attributes): static
    {
        if (!is_callable($attributes)) {
            $attributes = fn() => $attributes;
        }
        $this->script_tag_attributes_resolvers[] = $attributes;
        return $this;
    }
    /**
     * Use the given callback to resolve attributes for style tags.
     *
     * @param  (callable(string, string, ?array, ?array): array)|array  $attributes
     * @return $this
     */
    public function use_style_tag_attributes($attributes): static
    {
        if (!is_callable($attributes)) {
            $attributes = fn() => $attributes;
        }
        $this->style_tag_attributes_resolvers[] = $attributes;
        return $this;
    }
    /**
     * Use the given callback to resolve attributes for preload tags.
     *
     * @param  (callable(string, string, ?array, ?array): (array|false))|array|false  $attributes
     * @return $this
     */
    public function use_preload_tag_attributes($attributes): static
    {
        if (!is_callable($attributes)) {
            $attributes = fn() => $attributes;
        }
        $this->preload_tag_attributes_resolvers[] = $attributes;
        return $this;
    }
    /**
     * Eagerly prefetch assets.
     *
     * @param  int|null  $concurrency
     * @param  string  $event
     * @return $this
     */
    public function prefetch($concurrency = null, $event = 'load'): static
    {
        $this->prefetch_event = $event;
        return $concurrency === null ? $this->use_prefetch_strategy('aggressive') : $this->use_prefetch_strategy('waterfall', ['concurrency' => $concurrency]);
    }
    /**
     * Use the "waterfall" prefetching strategy.
     *
     * @return $this
     */
    public function use_waterfall_prefetching(?int $concurrency = null): static
    {
        return $this->use_prefetch_strategy('waterfall', ['concurrency' => $concurrency ?? $this->prefetch_concurrently]);
    }
    /**
     * Use the "aggressive" prefetching strategy.
     *
     * @return $this
     */
    public function use_aggressive_prefetching(): static
    {
        return $this->use_prefetch_strategy('aggressive');
    }
    /**
     * Set the prefetching strategy.
     *
     * @param  'waterfall'|'aggressive'|null  $strategy
     * @return $this
     */
    public function use_prefetch_strategy($strategy, array $config = []): static
    {
        $this->prefetch_strategy = $strategy;
        if ($strategy === 'waterfall') {
            $this->prefetch_concurrently = $config['concurrency'] ?? $this->prefetch_concurrently;
        }
        return $this;
    }
    /**
     * Generate Vite tags for an entrypoint.
     *
     * @param  string|string[]  $entrypoints
     * @param  string|null  $buildDirectory
     * @return \Illuminate\Support\HtmlString
     *
     * @throws \Exception
     */
    public function __invoke($entrypoints, $build_directory = null)
    {
        $entrypoints = new Collection($entrypoints);
        $build_directory ??= $this->build_directory;
        if ($this->is_running_hot()) {
            return new Html_String($entrypoints->prepend('@vite/client')->map(fn($entrypoint) => $this->make_tag_for_chunk($entrypoint, $this->hot_asset($entrypoint), null, null))->join(''));
        }
        $manifest = $this->manifest($build_directory);
        $tags = new Collection();
        $preloads = new Collection();
        foreach ($entrypoints as $entrypoint) {
            $chunk = $this->chunk($manifest, $entrypoint);
            $preloads->push([$chunk['src'], $this->asset_path("{$build_directory}/{$chunk['file']}"), $chunk, $manifest]);
            foreach ($chunk['imports'] ?? [] as $import) {
                $preloads->push([$import, $this->asset_path("{$build_directory}/{$manifest[$import]['file']}"), $manifest[$import], $manifest]);
                foreach ($manifest[$import]['css'] ?? [] as $css) {
                    $partial_manifest = (new Collection($manifest))->where('file', $css);
                    $preloads->push([$partial_manifest->keys()->first(), $this->asset_path("{$build_directory}/{$css}"), $partial_manifest->first(), $manifest]);
                    $tags->push($this->make_tag_for_chunk($partial_manifest->keys()->first(), $this->asset_path("{$build_directory}/{$css}"), $partial_manifest->first(), $manifest));
                }
            }
            $tags->push($this->make_tag_for_chunk($entrypoint, $this->asset_path("{$build_directory}/{$chunk['file']}"), $chunk, $manifest));
            foreach ($chunk['css'] ?? [] as $css) {
                $partial_manifest = (new Collection($manifest))->where('file', $css);
                $preloads->push([$partial_manifest->keys()->first(), $this->asset_path("{$build_directory}/{$css}"), $partial_manifest->first(), $manifest]);
                $tags->push($this->make_tag_for_chunk($partial_manifest->keys()->first(), $this->asset_path("{$build_directory}/{$css}"), $partial_manifest->first(), $manifest));
            }
        }
        [$stylesheets, $scripts] = $tags->unique()->partition(fn($tag): bool => str_starts_with((string) $tag, '<link'));
        $preloads = $preloads->unique()->sort_by_desc(fn($args): bool => $this->is_css_path($args[1]))->map(fn($args): string => $this->make_preload_tag_for_chunk(...$args));
        $base = $preloads->join('') . $stylesheets->join('') . $scripts->join('');
        if ($this->prefetch_strategy === null || $this->is_running_hot()) {
            return new Html_String($base);
        }
        $discovered_imports = [];
        return (new Collection($entrypoints))->flat_map(fn($entrypoint) => (new Collection($manifest[$entrypoint]['dynamicImports'] ?? []))->map(fn($import) => $manifest[$import])->filter(fn($chunk): bool => str_ends_with((string) $chunk['file'], '.js') || str_ends_with((string) $chunk['file'], '.css'))->flat_map($f = function (array $chunk) use (&$f, $manifest, &$discovered_imports) {
            return (new Collection([...$chunk['imports'] ?? [], ...$chunk['dynamicImports'] ?? []]))->reject(function ($import) use (&$discovered_imports): bool {
                if (isset($discovered_imports[$import])) {
                    return true;
                }
                return !$discovered_imports[$import] = true;
            })->reduce(fn($chunks, $import) => $chunks->merge($f($manifest[$import])), new Collection([$chunk]))->merge((new Collection($chunk['css'] ?? []))->map(fn($css) => (new Collection($manifest))->first(fn($chunk): bool => $chunk['file'] === $css) ?? ['file' => $css]));
        })->map(fn(array $chunk) => (new Collection([...$this->resolve_preload_tag_attributes($chunk['src'] ?? null, $url = $this->asset_path("{$build_directory}/{$chunk['file']}"), $chunk, $manifest), 'rel' => 'prefetch', 'fetchpriority' => 'low', 'href' => $url]))->reject(fn($value): bool => in_array($value, [null, false], true))->map_with_keys(fn($value, $key): array => [$key = is_int($key) ? $value : $key => $value === true ? $key : $value])->all())->reject(fn($attributes): bool => isset($this->preloaded_assets[$attributes['href']])))->unique('href')->values()->pipe(fn($assets) => with(Js::from($assets), fn($assets): \Illuminate\Support\Html_String => match ($this->prefetch_strategy) {
            'waterfall' => new Html_String($base . <<<HTML
            
            <script{$this->nonce_attribute()}>
                 window.addEventListener('{$this->prefetch_event}', () => window.setTimeout(() => {
                    const makeLink = (asset) => {
                        const link = document.createElement('link')
            
                        Object.keys(asset).forEach((attribute) => {
                            link.setAttribute(attribute, asset[attribute])
                        })
            
                        return link
                    }
            
                    const loadNext = (assets, count) => window.setTimeout(() => {
                        if (count > assets.length) {
                            count = assets.length
            
                            if (count === 0) {
                                return
                            }
                        }
            
                        const fragment = new DocumentFragment
            
                        while (count > 0) {
                            const link = makeLink(assets.shift())
                            fragment.append(link)
                            count--
            
                            if (assets.length) {
                                link.onload = () => loadNext(assets, 1)
                                link.onerror = () => loadNext(assets, 1)
                            }
                        }
            
                        document.head.append(fragment)
                    })
            
                    loadNext({$assets}, {$this->prefetch_concurrently})
                }))
            </script>
            HTML),
            'aggressive' => new Html_String($base . <<<HTML
            
            <script{$this->nonce_attribute()}>
                 window.addEventListener('{$this->prefetch_event}', () => window.setTimeout(() => {
                    const makeLink = (asset) => {
                        const link = document.createElement('link')
            
                        Object.keys(asset).forEach((attribute) => {
                            link.setAttribute(attribute, asset[attribute])
                        })
            
                        return link
                    }
            
                    const fragment = new DocumentFragment;
                    {$assets}.forEach((asset) => fragment.append(makeLink(asset)))
                    document.head.append(fragment)
                 }))
            </script>
            HTML),
        }));
    }
    /**
     * Make tag for the given chunk.
     *
     * @param  string  $src
     * @param  string  $url
     * @param  array|null  $chunk
     * @param  array|null  $manifest
     * @return string
     */
    protected function make_tag_for_chunk($src, $url, $chunk, $manifest)
    {
        if ($this->nonce === null && $this->integrity_key !== false && !array_key_exists($this->integrity_key, $chunk ?? []) && $this->script_tag_attributes_resolvers === [] && $this->style_tag_attributes_resolvers === []) {
            return $this->make_tag($url);
        }
        if ($this->is_css_path($url)) {
            return $this->make_stylesheet_tag_with_attributes($url, $this->resolve_stylesheet_tag_attributes($src, $url, $chunk, $manifest));
        }
        return $this->make_script_tag_with_attributes($url, $this->resolve_script_tag_attributes($src, $url, $chunk, $manifest));
    }
    /**
     * Make a preload tag for the given chunk.
     *
     * @param  string  $src
     * @param  string  $url
     * @param  array  $manifest
     */
    protected function make_preload_tag_for_chunk($src, $url, array $chunk, $manifest): string
    {
        $attributes = $this->resolve_preload_tag_attributes($src, $url, $chunk, $manifest);
        if ($attributes === false) {
            return '';
        }
        $this->preloaded_assets[$url] = $this->parse_attributes((new Collection($attributes))->forget('href')->all());
        return '<link ' . implode(' ', $this->parse_attributes($attributes)) . ' />';
    }
    /**
     * Resolve the attributes for the chunks generated script tag.
     *
     * @param  string  $src
     * @param  string  $url
     * @param  array|null  $chunk
     * @param  array|null  $manifest
     */
    protected function resolve_script_tag_attributes($src, $url, array $chunk, $manifest): array
    {
        $attributes = $this->integrity_key !== false ? ['integrity' => $chunk[$this->integrity_key] ?? false] : [];
        foreach ($this->script_tag_attributes_resolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }
        return $attributes;
    }
    /**
     * Resolve the attributes for the chunks generated stylesheet tag.
     *
     * @param  string  $src
     * @param  string  $url
     * @param  array|null  $chunk
     * @param  array|null  $manifest
     */
    protected function resolve_stylesheet_tag_attributes($src, $url, array $chunk, $manifest): array
    {
        $attributes = $this->integrity_key !== false ? ['integrity' => $chunk[$this->integrity_key] ?? false] : [];
        foreach ($this->style_tag_attributes_resolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }
        return $attributes;
    }
    /**
     * Resolve the attributes for the chunks generated preload tag.
     *
     * @param  string  $src
     * @param  string  $url
     * @param  array  $manifest
     * @return array|false
     */
    protected function resolve_preload_tag_attributes($src, $url, array $chunk, $manifest): false|array
    {
        $attributes = $this->is_css_path($url) ? ['rel' => 'preload', 'as' => 'style', 'href' => $url, 'nonce' => $this->nonce ?? false, 'crossorigin' => $this->resolve_stylesheet_tag_attributes($src, $url, $chunk, $manifest)['crossorigin'] ?? false] : ['rel' => 'modulepreload', 'as' => 'script', 'href' => $url, 'nonce' => $this->nonce ?? false, 'crossorigin' => $this->resolve_script_tag_attributes($src, $url, $chunk, $manifest)['crossorigin'] ?? false];
        $attributes = $this->integrity_key !== false ? array_merge($attributes, ['integrity' => $chunk[$this->integrity_key] ?? false]) : $attributes;
        foreach ($this->preload_tag_attributes_resolvers as $resolver) {
            if (false === $resolved_attributes = $resolver($src, $url, $chunk, $manifest)) {
                return false;
            }
            $attributes = array_merge($attributes, $resolved_attributes);
        }
        return $attributes;
    }
    /**
     * Generate an appropriate tag for the given URL in HMR mode.
     *
     * @deprecated Will be removed in a future Laravel version.
     *
     * @param  string  $url
     * @return string
     */
    protected function make_tag($url)
    {
        if ($this->is_css_path($url)) {
            return $this->make_stylesheet_tag($url);
        }
        return $this->make_script_tag($url);
    }
    /**
     * Generate a script tag for the given URL.
     *
     * @deprecated Will be removed in a future Laravel version.
     *
     * @param  string  $url
     */
    protected function make_script_tag($url): string
    {
        return $this->make_script_tag_with_attributes($url, []);
    }
    /**
     * Generate a stylesheet tag for the given URL in HMR mode.
     *
     * @deprecated Will be removed in a future Laravel version.
     *
     * @param  string  $url
     */
    protected function make_stylesheet_tag($url): string
    {
        return $this->make_stylesheet_tag_with_attributes($url, []);
    }
    /**
     * Generate a script tag with attributes for the given URL.
     *
     * @param  string  $url
     * @param  array  $attributes
     */
    protected function make_script_tag_with_attributes($url, $attributes): string
    {
        $attributes = $this->parse_attributes(array_merge(['type' => 'module', 'src' => $url, 'nonce' => $this->nonce ?? false], $attributes));
        return '<script ' . implode(' ', $attributes) . '></script>';
    }
    /**
     * Generate a link tag with attributes for the given URL.
     *
     * @param  string  $url
     * @param  array  $attributes
     */
    protected function make_stylesheet_tag_with_attributes($url, $attributes): string
    {
        $attributes = $this->parse_attributes(array_merge(['rel' => 'stylesheet', 'href' => $url, 'nonce' => $this->nonce ?? false], $attributes));
        return '<link ' . implode(' ', $attributes) . ' />';
    }
    /**
     * Determine whether the given path is a CSS file.
     *
     * @param  string  $path
     */
    protected function is_css_path($path): bool
    {
        return preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)(\?[^\.]*)?$/', $path) === 1;
    }
    /**
     * Parse the attributes into key="value" strings.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function parse_attributes($attributes)
    {
        return (new Collection($attributes))->reject(fn($value, $key): bool => in_array($value, [false, null], true))->flat_map(fn($value, $key): array => $value === true ? [$key] : [$key => $value])->map(fn($value, $key) => is_int($key) ? $value : $key . '="' . $value . '"')->values()->all();
    }
    /**
     * Generate React refresh runtime script.
     *
     * @return \Illuminate\Support\HtmlString|void
     */
    public function react_refresh()
    {
        if (!$this->is_running_hot()) {
            return;
        }
        $attributes = $this->parse_attributes(['nonce' => $this->csp_nonce()]);
        return new Html_String(sprintf(<<<'HTML'
        <script type="module" %s>
            import RefreshRuntime from '%s'
            RefreshRuntime.injectIntoGlobalHook(window)
            window.$RefreshReg$ = () => {}
            window.$RefreshSig$ = () => (type) => type
            window.__vite_plugin_react_preamble_installed__ = true
        </script>
        HTML, implode(' ', $attributes), $this->hot_asset('@react-refresh')));
    }
    /**
     * Get the path to a given asset when running in HMR mode.
     */
    protected function hot_asset(string $asset): string
    {
        return rtrim(file_get_contents($this->hot_file())) . '/' . $asset;
    }
    /**
     * Get the URL for an asset.
     *
     * @param  string  $asset
     * @param  string|null  $buildDirectory
     * @return string
     */
    public function asset($asset, $build_directory = null)
    {
        $build_directory ??= $this->build_directory;
        if ($this->is_running_hot()) {
            return $this->hot_asset($asset);
        }
        $chunk = $this->chunk($this->manifest($build_directory), $asset);
        return $this->asset_path($build_directory . '/' . $chunk['file']);
    }
    /**
     * Get the content of a given asset.
     *
     * @param  string  $asset
     * @param  string|null  $buildDirectory
     * @return string
     *
     * @throws \Illuminate\Foundation\ViteException
     */
    public function content($asset, $build_directory = null): string|false
    {
        $build_directory ??= $this->build_directory;
        $chunk = $this->chunk($this->manifest($build_directory), $asset);
        $path = $this->public_path($build_directory . '/' . $chunk['file']);
        if (!is_file($path) || !file_exists($path)) {
            throw new Vite_Exception("Unable to locate file from Vite manifest: {$path}.");
        }
        return file_get_contents($path);
    }
    /**
     * Generate an asset path for the application.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    protected function asset_path($path, $secure = null)
    {
        return ($this->asset_path_resolver ?? asset(...))($path, $secure);
    }
    /**
     * Generate a public path for an asset.
     *
     * @param  string  $path
     */
    protected function public_path($path): string
    {
        return public_path($path);
    }
    /**
     * Get the manifest file for the given build directory.
     *
     * @return array
     * @throws \Illuminate\Foundation\ViteManifestNotFoundException
     */
    protected function manifest(string $build_directory)
    {
        $path = $this->manifest_path($build_directory);
        if (!isset(static::$manifests[$path])) {
            if (!is_file($path)) {
                throw new Vite_Manifest_Not_Found_Exception("Vite manifest not found at: {$path}");
            }
            static::$manifests[$path] = json_decode(file_get_contents($path), true);
        }
        return static::$manifests[$path];
    }
    /**
     * Get the path to the manifest file for the given build directory.
     */
    protected function manifest_path(string $build_directory): string
    {
        return public_path($build_directory . '/' . $this->manifest_filename);
    }
    /**
     * Get a unique hash representing the current manifest, or null if there is no manifest.
     *
     * @param  string|null  $buildDirectory
     * @return string|null
     */
    public function manifest_hash($build_directory = null)
    {
        $build_directory ??= $this->build_directory;
        if ($this->is_running_hot()) {
            return null;
        }
        if (!is_file($path = $this->manifest_path($build_directory))) {
            return null;
        }
        return md5_file($path) ?: null;
    }
    /**
     * Get the chunk for the given entry point / asset.
     *
     * @param  string  $file
     * @return array
     * @throws \Illuminate\Foundation\ViteException
     */
    protected function chunk(array $manifest, $file)
    {
        if (!isset($manifest[$file])) {
            throw new Vite_Exception("Unable to locate file in Vite manifest: {$file}.");
        }
        return $manifest[$file];
    }
    /**
     * Get the nonce attribute for the prefetch script tags.
     */
    protected function nonce_attribute(): \Illuminate\Support\Html_String
    {
        if ($this->csp_nonce() === null) {
            return new Html_String('');
        }
        return new Html_String(' nonce="' . $this->csp_nonce() . '"');
    }
    /**
     * Determine if the HMR server is running.
     */
    public function is_running_hot(): bool
    {
        return is_file($this->hot_file());
    }
    /**
     * Get the Vite tag content as a string of HTML.
     *
     * @return string
     */
    public function to_html()
    {
        return $this->__invoke($this->entry_points)->to_html();
    }
    /**
     * Flush state.
     */
    public function flush(): void
    {
        $this->preloaded_assets = [];
    }
}