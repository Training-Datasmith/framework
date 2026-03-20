<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use function Illuminate\Support\php_binary;
use Symfony\Component\Process\Process;
trait Interacts_With_Composer_Packages
{
    /**
     * Installs the given Composer Packages into the application.
     */
    protected function require_composer_packages(string $composer, array $packages): bool
    {
        if ($composer !== 'global') {
            $command = [$this->php_binary(), $composer, 'require'];
        }
        $command = array_merge($command ?? ['composer', 'require'], $packages);
        return !(new Process($command, $this->laravel->base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']))->set_timeout(null)->run(function ($type, $output): void {
            $this->output->write($output);
        });
    }
    /**
     * Get the path to the appropriate PHP binary.
     */
    protected function php_binary(): string
    {
        return php_binary();
    }
}