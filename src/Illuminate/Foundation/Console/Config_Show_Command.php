<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'config:show')]
class Config_Show_Command extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'config:show {config : The configuration file or key to show}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all of the values for a given configuration file or key';
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $config = $this->argument('config');
        if (!config()->has($config)) {
            $this->fail("Configuration file or key <comment>{$config}</comment> does not exist.");
        }
        $this->new_line();
        $this->render($config);
        $this->new_line();
        return Command::SUCCESS;
    }
    /**
     * Render the configuration values.
     *
     * @param  string  $name
     */
    public function render($name): void
    {
        $data = config($name);
        if (!is_array($data)) {
            $this->title($name, $this->format_value($data));
            return;
        }
        $this->title($name);
        foreach (Arr::dot($data) as $key => $value) {
            $this->components->two_column_detail($this->format_key($key), $this->format_value($value));
        }
    }
    /**
     * Render the title.
     *
     * @param  string  $title
     * @param  string|null  $subtitle
     */
    public function title($title, $subtitle = null): void
    {
        $this->components->two_column_detail("<fg=green;options=bold>{$title}</>", $subtitle);
    }
    /**
     * Format the given configuration key.
     *
     * @param  string  $key
     * @return string
     */
    protected function format_key($key): ?string
    {
        return preg_replace_callback('/(.*)\.(.*)$/', fn($matches): string => sprintf('<fg=gray>%s ⇁</> %s', str_replace('.', ' ⇁ ', $matches[1]), $matches[2]), $key);
    }
    /**
     * Format the given configuration value.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function format_value($value): string|true
    {
        return match (true) {
            is_bool($value) => sprintf('<fg=#ef8414;options=bold>%s</>', $value ? 'true' : 'false'),
            is_null($value) => '<fg=#ef8414;options=bold>null</>',
            is_numeric($value) => "<fg=#ef8414;options=bold>{$value}</>",
            is_array($value) => '[]',
            is_object($value) => $value::class,
            is_string($value) => $value,
            default => print_r($value, true),
        };
    }
}