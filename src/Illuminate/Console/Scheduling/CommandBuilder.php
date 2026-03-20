<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Console\Application;
use Illuminate\Support\Process_Utils;
class Command_Builder
{
    /**
     * Build the command for the given event.
     *
     * @return string
     */
    public function build_command(Event $event)
    {
        if ($event->run_in_background) {
            return $this->build_background_command($event);
        }
        return $this->build_foreground_command($event);
    }
    /**
     * Build the command for running the event in the foreground.
     */
    protected function build_foreground_command(Event $event): string
    {
        $output = Process_Utils::escape_argument($event->output);
        return laravel_cloud() ? $this->ensure_correct_user($event, $event->command . ' 2>&1 | tee ' . ($event->should_append_output ? '-a ' : '') . $output) : $this->ensure_correct_user($event, $event->command . ($event->should_append_output ? ' >> ' : ' > ') . $output . ' 2>&1');
    }
    /**
     * Build the command for running the event in the background.
     */
    protected function build_background_command(Event $event): string
    {
        $output = Process_Utils::escape_argument($event->output);
        $redirect = $event->should_append_output ? ' >> ' : ' > ';
        $finished = Application::format_command_string('schedule:finish') . ' "' . $event->mutex_name() . '"';
        if (windows_os()) {
            return 'start /b cmd /v:on /c "(' . $event->command . ' & ' . $finished . ' ^!ERRORLEVEL^!)' . $redirect . $output . ' 2>&1"';
        }
        return $this->ensure_correct_user($event, '(' . $event->command . $redirect . $output . ' 2>&1 ; ' . $finished . ' "$?") > ' . Process_Utils::escape_argument($event->get_default_output()) . ' 2>&1 &');
    }
    /**
     * Finalize the event's command syntax with the correct user.
     */
    protected function ensure_correct_user(Event $event, string $command): string
    {
        return $event->user && !windows_os() ? 'sudo -u ' . escapeshellarg($event->user) . ' -- sh -c \'' . $command . '\'' : $command;
    }
}