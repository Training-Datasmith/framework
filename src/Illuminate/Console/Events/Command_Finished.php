<?php

declare (strict_types=1);
namespace Illuminate\Console\Events;

use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
class Command_Finished
{
    /**
     * Create a new event instance.
     *
     * @param  string  $command  The command name.
     * @param  \Symfony\Component\Console\Input\InputInterface  $input  The console input implementation.
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output  The command output implementation.
     * @param  int  $exitCode  The command exit code.
     */
    public function __construct(public string $command, public Input_Interface $input, public Output_Interface $output, public int $exit_code)
    {
    }
}