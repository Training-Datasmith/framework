<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Carbon\Carbon_Interval;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Str;
use function Laravel\Prompts\suggest;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Process\Exception\Process_Failed_Exception;
use Symfony\Component\Process\Executable_Finder;
use Symfony\Component\Process\Process;
use Throwable;
#[As_Command(name: 'docs')]
class Docs_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs {page? : The documentation page to open} {section? : The section of the page to open}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Access the Laravel documentation';
    /**
     * The console command help text.
     *
     * @var string
     */
    protected $help = 'If you would like to perform a content search against the documentation, you may call: <fg=green>php artisan docs -- </><fg=green;options=bold;>search query here</>';
    /**
     * The HTTP client instance.
     *
     * @var \Illuminate\Http\Client\Factory
     */
    protected $http;
    /**
     * The cache repository implementation.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;
    /**
     * The custom URL opener.
     *
     * @var callable|null
     */
    protected $url_opener;
    /**
     * The custom documentation version to open.
     *
     * @var string|null
     */
    protected $version;
    /**
     * The operating system family.
     *
     * @var string
     */
    protected $system_os_family = PHP_OS_FAMILY;
    /**
     * Configure the current command.
     */
    protected function configure(): void
    {
        parent::configure();
        if ($this->is_searching()) {
            $this->ignore_validation_errors();
        }
    }
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(Http $http, Cache $cache): ?int
    {
        $this->http = $http;
        $this->cache = $cache;
        try {
            $this->open_url();
        } catch (Process_Failed_Exception $e) {
            if ($e->get_process()->get_exit_code_text() === 'Interrupt') {
                return $e->get_process()->get_exit_code();
            }
            throw $e;
        }
        $this->refresh_docs();
        return Command::SUCCESS;
    }
    /**
     * Open the documentation URL.
     *
     * @return void
     */
    protected function open_url()
    {
        $url = $this->url();
        $this->components->info("Opening the docs to: <fg=yellow>{$url}</>");
        $this->open($url);
    }
    /**
     * The URL to the documentation page.
     */
    protected function url(): string
    {
        if ($this->is_searching()) {
            return "https://laravel.com/docs/{$this->version()}?" . Arr::query(['q' => $this->search_query()]);
        }
        $page = $this->page();
        return trim("https://laravel.com/docs/{$this->version()}/{$page}#{$this->section($page)}", '#/');
    }
    /**
     * The page the user is opening.
     *
     * @return string
     */
    protected function page()
    {
        $page = $this->resolve_page();
        if ($page === null) {
            $this->components->warn('Unable to determine the page you are trying to visit.');
            return '/';
        }
        return $page;
    }
    /**
     * Determine the page to open.
     *
     * @return string|null
     */
    protected function resolve_page()
    {
        if ($this->option('no-interaction') && $this->did_not_request_page()) {
            return '/';
        }
        return $this->did_not_request_page() ? $this->ask_for_page() : $this->guess_page($this->argument('page'));
    }
    /**
     * Determine if the user requested a specific page when calling the command.
     */
    protected function did_not_request_page(): bool
    {
        return $this->argument('page') === null;
    }
    /**
     * Ask the user which page they would like to open.
     *
     * @return string|null
     */
    protected function ask_for_page()
    {
        return $this->ask_for_page_via_custom_strategy() ?? $this->ask_for_page_via_autocomplete();
    }
    /**
     * Ask the user which page they would like to open via a custom strategy.
     *
     * @return string|null
     */
    protected function ask_for_page_via_custom_strategy()
    {
        try {
            $strategy = require Env::get('ARTISAN_DOCS_ASK_STRATEGY');
        } catch (Throwable) {
            return null;
        }
        if (!is_callable($strategy)) {
            return null;
        }
        return $strategy($this) ?? '/';
    }
    /**
     * Ask the user which page they would like to open using autocomplete.
     *
     * @return string|null
     */
    protected function ask_for_page_via_autocomplete()
    {
        $choice = suggest(label: 'Which page would you like to open?', options: fn($value) => $this->pages()->map_with_keys(fn($option): array => [Str::lower($option['title']) => $option['title']])->filter(fn($title): bool => str_contains(Str::lower($title), Str::lower($value)))->all(), placeholder: 'E.g. Collections');
        return $this->pages()->filter(fn($page): bool => $page['title'] === $choice || Str::lower($page['title']) === $choice)->keys()->first() ?: $this->guess_page($choice);
    }
    /**
     * Guess the page the user is attempting to open.
     *
     * @return string|null
     */
    protected function guess_page($search)
    {
        return $this->pages()->filter(fn($page): bool => str_starts_with(Str::slug($page['title'], ' '), Str::slug($search, ' ')))->keys()->first() ?? $this->pages()->map(fn($page): int => similar_text(Str::slug($page['title'], ' '), Str::slug($search, ' ')))->filter(fn($score): bool => $score >= min(3, Str::length($search)))->sort_desc()->keys()->sort_by_desc(fn($slug): int => Str::contains(Str::slug($this->pages()[$slug]['title'], ' '), Str::slug($search, ' ')) ? 1 : 0)->first();
    }
    /**
     * The section the user specifically asked to open.
     *
     * @param  string  $page
     * @return string|null
     */
    protected function section($page)
    {
        return $this->did_not_request_section() ? null : $this->guess_section($page);
    }
    /**
     * Determine if the user requested a specific section when calling the command.
     */
    protected function did_not_request_section(): bool
    {
        return $this->argument('section') === null;
    }
    /**
     * Guess the section the user is attempting to open.
     *
     * @param  string  $page
     * @return string|null
     */
    protected function guess_section($page)
    {
        return $this->sections_for($page)->filter(fn($section): bool => str_starts_with(Str::slug($section['title'], ' '), Str::slug($this->argument('section'), ' ')))->keys()->first() ?? $this->sections_for($page)->map(fn($section): int => similar_text(Str::slug($section['title'], ' '), Str::slug($this->argument('section'), ' ')))->filter(fn($score): bool => $score >= min(3, Str::length($this->argument('section'))))->sort_desc()->keys()->sort_by_desc(fn($slug): int => Str::contains(Str::slug($this->sections_for($page)[$slug]['title'], ' '), Str::slug($this->argument('section'), ' ')) ? 1 : 0)->first();
    }
    /**
     * Open the URL in the user's browser.
     *
     * @param  string  $url
     * @return void
     */
    protected function open($url)
    {
        ($this->url_opener ?? function ($url): void {
            if (Env::get('ARTISAN_DOCS_OPEN_STRATEGY')) {
                $this->open_via_custom_strategy($url);
            } elseif (in_array($this->system_os_family, ['Darwin', 'Windows', 'Linux'])) {
                $this->open_via_built_in_strategy($url);
            } else {
                $this->components->warn('Unable to open the URL on your system. You will need to open it yourself or create a custom opener for your system.');
            }
        })($url);
    }
    /**
     * Open the URL via a custom strategy.
     *
     * @param  string  $url
     * @return void
     */
    protected function open_via_custom_strategy($url)
    {
        try {
            $command = require Env::get('ARTISAN_DOCS_OPEN_STRATEGY');
        } catch (Throwable) {
            $command = null;
        }
        if (!is_callable($command)) {
            $this->components->warn('Unable to open the URL with your custom strategy. You will need to open it yourself.');
            return;
        }
        $command($url);
    }
    /**
     * Open the URL via the built in strategy.
     *
     * @param  string  $url
     * @return void
     */
    protected function open_via_built_in_strategy($url)
    {
        if ($this->system_os_family === 'Windows') {
            $process = tap(Process::from_shell_commandline(escapeshellcmd("start {$url}")))->run();
            if (!$process->is_successful()) {
                throw new Process_Failed_Exception($process);
            }
            return;
        }
        $binary = (new Collection(match ($this->system_os_family) {
            'Darwin' => ['open'],
            'Linux' => ['xdg-open', 'wslview'],
        }))->first(fn(string $binary): bool => (new Executable_Finder())->find($binary) !== null);
        if ($binary === null) {
            $this->components->warn('Unable to open the URL on your system. You will need to open it yourself or create a custom opener for your system.');
            return;
        }
        $process = tap(Process::from_shell_commandline(escapeshellcmd("{$binary} {$url}")))->run();
        if (!$process->is_successful()) {
            throw new Process_Failed_Exception($process);
        }
    }
    /**
     * The available sections for the page.
     *
     * @param  string  $page
     */
    public function sections_for($page): \Illuminate\Support\Collection
    {
        return new Collection($this->pages()[$page]['sections']);
    }
    /**
     * The pages available to open.
     */
    public function pages(): \Illuminate\Support\Collection
    {
        return new Collection($this->docs()['pages']);
    }
    /**
     * Get the documentation index as a collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function docs()
    {
        return $this->cache->remember("artisan.docs.{{$this->version()}}.index", Carbon_Interval::months(2), fn() => $this->fetch_docs()->throw()->collect());
    }
    /**
     * Refresh the cached copy of the documentation index.
     *
     * @return void
     */
    protected function refresh_docs()
    {
        $response = $this->fetch_docs();
        if ($response->successful()) {
            $this->cache->put("artisan.docs.{{$this->version()}}.index", $response->collect(), Carbon_Interval::months(2));
        }
    }
    /**
     * Fetch the documentation index from the Laravel website.
     *
     * @return \Illuminate\Http\Client\Response
     */
    protected function fetch_docs()
    {
        return $this->http->get("https://laravel.com/docs/{$this->version()}/index.json");
    }
    /**
     * Determine the version of the docs to open.
     */
    protected function version(): string
    {
        return Str::before($this->version ?? $this->laravel->version(), '.') . '.x';
    }
    /**
     * The search query the user provided.
     */
    protected function search_query(): string
    {
        return (new Collection($_SERVER['argv']))->skip(3)->implode(' ');
    }
    /**
     * Determine if the command is intended to perform a search.
     */
    protected function is_searching(): bool
    {
        return ($_SERVER['argv'][2] ?? null) === '--';
    }
    /**
     * Set the documentation version.
     *
     * @param  string  $version
     * @return $this
     */
    public function set_version($version): static
    {
        $this->version = $version;
        return $this;
    }
    /**
     * Set a custom URL opener.
     *
     * @param  callable|null  $opener
     * @return $this
     */
    public function set_url_opener($opener): static
    {
        $this->url_opener = $opener;
        return $this;
    }
    /**
     * Set the system operating system family.
     *
     * @param  string  $family
     * @return $this
     */
    public function set_system_os_family($family): static
    {
        $this->system_os_family = $family;
        return $this;
    }
}