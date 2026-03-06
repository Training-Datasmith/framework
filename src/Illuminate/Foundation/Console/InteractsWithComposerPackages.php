<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Console;

use function Illuminate\Support\php_binary;

use Symfony\Component\Process\Process;

trait InteractsWithComposerPackages
{
    /**
     * Installs the given Composer Packages into the application.
     */
    protected function requireComposerPackages(string $composer, array $packages): bool
    {
        if ($composer !== 'global') {
            $command = [$this->phpBinary(), $composer, 'require'];
        }

        $command = array_merge(
            $command ?? ['composer', 'require'],
            $packages,
        );

        return ! (new Process($command, $this->laravel->basePath(), ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output): void {
                $this->output->write($output);
            });
    }

    /**
     * Get the path to the appropriate PHP binary.
     */
    protected function phpBinary(): string
    {
        return php_binary();
    }
}
