<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Terminal;
#[As_Command(name: 'channel:list')]
class Channel_List_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'channel:list';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered private broadcast channels';
    /**
     * The terminal width resolver callback.
     *
     * @var \Closure|null
     */
    protected static $terminal_width_resolver;
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(Broadcaster $broadcaster)
    {
        $channels = $broadcaster->get_channels();
        if (!$this->laravel->provider_is_loaded('App\Providers\BroadcastServiceProvider') && file_exists($this->laravel->path('Providers/BroadcastServiceProvider.php'))) {
            $this->components->warn('The [App\Providers\BroadcastServiceProvider] has not been loaded. Your private channels may not be loaded.');
        }
        if (!$channels->count()) {
            return $this->components->error("Your application doesn't have any private broadcasting channels.");
        }
        $this->display_channels($channels);
    }
    /**
     * Display the channel information on the console.
     *
     * @param  Collection  $channels
     * @return void
     */
    protected function display_channels($channels)
    {
        $this->output->writeln($this->for_cli($channels));
    }
    /**
     * Convert the given channels to regular CLI output.
     *
     * @param  \Illuminate\Support\Collection  $channels
     * @return array
     */
    protected function for_cli($channels)
    {
        $max_channel_name = $channels->keys()->max(fn($channel_name): int => mb_strlen((string) $channel_name));
        $terminal_width = static::get_terminal_width();
        $channel_count = $this->determine_channel_count_output($channels, $terminal_width);
        return $channels->map(function ($channel, string $channel_name) use ($max_channel_name, $terminal_width): string {
            $resolver = $channel instanceof Closure ? 'Closure' : $channel;
            $spaces = str_repeat(' ', max($max_channel_name + 6 - mb_strlen($channel_name), 0));
            $dots = str_repeat('.', max($terminal_width - mb_strlen($channel_name . $spaces . $resolver) - 6, 0));
            $dots = empty($dots) ? $dots : " {$dots}";
            return sprintf('  <fg=blue;options=bold>%s</> %s<fg=white>%s</><fg=#6C7280>%s</>', $channel_name, $spaces, $resolver, $dots);
        })->filter()->sort()->prepend('')->push('')->push($channel_count)->push('')->to_array();
    }
    /**
     * Determine and return the output for displaying the number of registered channels in the CLI output.
     *
     * @param  \Illuminate\Support\Collection  $channels
     * @param  int  $terminalWidth
     */
    protected function determine_channel_count_output($channels, $terminal_width): string
    {
        $channel_count_text = 'Showing [' . $channels->count() . '] private channels';
        $offset = $terminal_width - mb_strlen($channel_count_text) - 2;
        $spaces = str_repeat(' ', $offset);
        return $spaces . '<fg=blue;options=bold>Showing [' . $channels->count() . '] private channels</>';
    }
    /**
     * Get the terminal width.
     *
     * @return int
     */
    public static function get_terminal_width()
    {
        return is_null(static::$terminal_width_resolver) ? (new Terminal())->get_width() : call_user_func(static::$terminal_width_resolver);
    }
    /**
     * Set a callback that should be used when resolving the terminal width.
     *
     * @param  \Closure|null  $resolver
     */
    public static function resolve_terminal_width_using($resolver): void
    {
        static::$terminal_width_resolver = $resolver;
    }
}