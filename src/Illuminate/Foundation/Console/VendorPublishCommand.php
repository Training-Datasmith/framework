<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Events\Vendor_Tag_Published;
use Illuminate\Support\Arr;
use Illuminate\Support\Service_Provider;
use Illuminate\Support\Str;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\Local_Filesystem_Adapter as LocalAdapter;
use League\Flysystem\Mount_Manager;
use League\Flysystem\Unix_Visibility\Portable_Visibility_Converter;
use League\Flysystem\Visibility;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'vendor:publish')]
class Vendor_Publish_Command extends Command
{
    /**
     * The provider to publish.
     *
     * @var string|null
     */
    protected $provider;
    /**
     * The tags to publish.
     *
     * @var array
     */
    protected $tags = [];
    /**
     * The time the command started.
     *
     * @var \Illuminate\Support\Carbon|null
     */
    protected $published_at;
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'vendor:publish
                    {--existing : Publish and overwrite only the files that have already been published}
                    {--force : Overwrite any existing files}
                    {--all : Publish assets for all service providers without prompt}
                    {--provider= : The service provider that has assets you want to publish}
                    {--tag=* : One or many tags that have assets you want to publish}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish any publishable assets from vendor packages';
    /**
     * Indicates if migration dates should be updated while publishing.
     *
     * @var bool
     */
    protected static $update_migration_dates = true;
    /**
     * Create a new command instance.
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->published_at = now();
        $this->determine_what_should_be_published();
        foreach ($this->tags ?: [null] as $tag) {
            $this->publish_tag($tag);
        }
    }
    /**
     * Determine the provider or tag(s) to publish.
     *
     * @return void
     */
    protected function determine_what_should_be_published()
    {
        if ($this->option('all')) {
            return;
        }
        [$this->provider, $this->tags] = [$this->option('provider'), (array) $this->option('tag')];
        if (!$this->provider && !$this->tags) {
            $this->prompt_for_provider_or_tag();
        }
    }
    /**
     * Prompt for which provider or tag to publish.
     *
     * @return void
     */
    protected function prompt_for_provider_or_tag()
    {
        $choices = $this->publishable_choices();
        $choice = windows_os() ? select("Which provider or tag's files would you like to publish?", $choices, scroll: 15) : search(label: "Which provider or tag's files would you like to publish?", placeholder: 'Search...', options: fn($search): array => array_values(array_filter($choices, fn($choice): bool => str_contains(strtolower((string) $choice), strtolower((string) $search)))), scroll: 15);
        if ($choice == $choices[0] || is_null($choice)) {
            return;
        }
        $this->parse_choice($choice);
    }
    /**
     * The choices available via the prompt.
     */
    protected function publishable_choices(): array
    {
        return array_merge(['All providers and tags'], preg_filter('/^/', '<fg=gray>Provider:</> ', Arr::sort(Service_Provider::publishable_providers())), preg_filter('/^/', '<fg=gray>Tag:</> ', Arr::sort(Service_Provider::publishable_groups())));
    }
    /**
     * Parse the answer that was given via the prompt.
     *
     * @param  string  $choice
     * @return void
     */
    protected function parse_choice($choice)
    {
        [$type, $value] = explode(': ', strip_tags($choice));
        if ($type === 'Provider') {
            $this->provider = $value;
        } elseif ($type === 'Tag') {
            $this->tags = [$value];
        }
    }
    /**
     * Publishes the assets for a tag.
     *
     * @return void
     */
    protected function publish_tag(string $tag)
    {
        $paths_to_publish = $this->paths_to_publish($tag);
        if ($publishing = count($paths_to_publish) > 0) {
            $this->components->info(sprintf('Publishing %sassets', $tag ? "[{$tag}] " : ''));
        }
        foreach ($paths_to_publish as $from => $to) {
            $this->publish_item($from, $to);
        }
        if ($publishing === false) {
            $this->components->info('No publishable resources for tag [' . $tag . '].');
        } else {
            $this->laravel['events']->dispatch(new Vendor_Tag_Published($tag, $paths_to_publish));
            $this->new_line();
        }
    }
    /**
     * Get all of the paths to publish.
     *
     * @param  string  $tag
     * @return array
     */
    protected function paths_to_publish($tag)
    {
        return Service_Provider::paths_to_publish($this->provider, $tag);
    }
    /**
     * Publish the given item from and to the given location.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    protected function publish_item($from, $to)
    {
        if ($this->files->is_file($from)) {
            return $this->publish_file($from, $to);
        }
        if ($this->files->is_directory($from)) {
            return $this->publish_directory($from, $to);
        }
        $this->components->error("Can't locate path: <{$from}>");
    }
    /**
     * Publish the file to the given path.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    protected function publish_file($from, $to)
    {
        if (!$this->option('existing') && (!$this->files->exists($to) || $this->option('force')) || $this->option('existing') && $this->files->exists($to)) {
            $to = $this->ensure_migration_name_is_up_to_date($from, $to);
            $this->create_parent_directory(dirname($to));
            $this->files->copy($from, $to);
            $this->status($from, $to, 'file');
        } else if ($this->option('existing')) {
            $this->components->two_column_detail(sprintf('File [%s] does not exist', str_replace(base_path() . '/', '', $to)), '<fg=yellow;options=bold>SKIPPED</>');
        } else {
            $this->components->two_column_detail(sprintf('File [%s] already exists', str_replace(base_path() . '/', '', realpath($to))), '<fg=yellow;options=bold>SKIPPED</>');
        }
    }
    /**
     * Publish the directory to the given directory.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    protected function publish_directory($from, $to)
    {
        $visibility = Portable_Visibility_Converter::from_array([], Visibility::PUBLIC);
        $this->move_managed_files($from, new Mount_Manager(['from' => new Flysystem(new Local_Adapter($from)), 'to' => new Flysystem(new Local_Adapter($to, $visibility))]));
        $this->status($from, $to, 'directory');
    }
    /**
     * Move all the files in the given MountManager.
     *
     * @param  string  $from
     * @param  \League\Flysystem\MountManager  $manager
     * @return void
     */
    protected function move_managed_files($from, $manager)
    {
        foreach ($manager->list_contents('from://', true)->sort_by_path() as $file) {
            $path = Str::after($file['path'], 'from://');
            if ($file['type'] === 'file' && (!$this->option('existing') && (!$manager->file_exists('to://' . $path) || $this->option('force')) || $this->option('existing') && $manager->file_exists('to://' . $path))) {
                $path = $this->ensure_migration_name_is_up_to_date($from, $path);
                $manager->write('to://' . $path, $manager->read($file['path']));
            }
        }
    }
    /**
     * Create the directory to house the published files if needed.
     *
     * @param  string  $directory
     * @return void
     */
    protected function create_parent_directory($directory)
    {
        if (!$this->files->is_directory($directory)) {
            $this->files->make_directory($directory, 0755, true);
        }
    }
    /**
     * Ensure the given migration name is up-to-date.
     *
     * @param  string  $from
     * @param  string  $to
     * @return string
     */
    protected function ensure_migration_name_is_up_to_date($from, $to)
    {
        if (static::$update_migration_dates === false) {
            return $to;
        }
        $from = realpath($from);
        foreach (Service_Provider::publishable_migration_paths() as $path) {
            $path = realpath($path);
            if ($from === $path && preg_match('/\d{4}_(\d{2})_(\d{2})_(\d{6})_/', $to)) {
                $this->published_at = $this->published_at->add_second();
                return preg_replace('/\d{4}_(\d{2})_(\d{2})_(\d{6})_/', $this->published_at->format('Y_m_d_His') . '_', $to);
            }
        }
        return $to;
    }
    /**
     * Write a status message to the console.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    protected function status($from, $to, string $type)
    {
        $from = str_replace(base_path() . '/', '', realpath($from));
        $to = str_replace(base_path() . '/', '', realpath($to));
        $this->components->task(sprintf('Copying %s [%s] to [%s]', $type, $from, $to));
    }
    /**
     * Instruct the command to not update the dates on migrations when publishing.
     */
    public static function dont_update_migration_dates(): void
    {
        static::$update_migration_dates = false;
    }
}