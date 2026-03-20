<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Console\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Process_Utils;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Process\Process;
#[As_Command(name: 'schedule:work')]
class Schedule_Work_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:work
        {--run-output-file= : The file to direct <info>schedule:run</info> output to}
        {--whisper : Do not output message indicating that no jobs were ready to run}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the schedule worker';
    /**
     * Execute the console command.
     *
     * @return never
     */
    public function handle(): void
    {
        $this->components->info('Running scheduled tasks.', $this->get_laravel()->environment('local') ? Output_Interface::VERBOSITY_NORMAL : Output_Interface::VERBOSITY_VERBOSE);
        [$last_execution_started_at, $executions] = [Carbon::now()->sub_minutes(10), []];
        $command = Application::format_command_string('schedule:run');
        if ($this->option('whisper')) {
            $command .= ' --whisper';
        }
        if ($this->option('run-output-file')) {
            $command .= ' >> ' . Process_Utils::escape_argument($this->option('run-output-file')) . ' 2>&1';
        }
        while (true) {
            usleep(100 * 1000);
            if (Carbon::now()->second === 0 && !Carbon::now()->start_of_minute()->equal_to($last_execution_started_at)) {
                $executions[] = $execution = Process::from_shell_commandline($command, base_path());
                $execution->start();
                $last_execution_started_at = Carbon::now()->start_of_minute();
            }
            foreach ($executions as $key => $execution) {
                $output = $execution->get_incremental_output() . $execution->get_incremental_error_output();
                $this->output->write(ltrim($output, "\n"));
                if (!$execution->is_running()) {
                    unset($executions[$key]);
                }
            }
        }
    }
}